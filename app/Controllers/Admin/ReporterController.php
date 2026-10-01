<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Bureau;
use App\Models\Category;
use App\Models\Location;
use App\Models\Reporter;
use App\Models\ReporterApplication;
use App\Models\ReporterDocument;
use App\Services\AuditService;
use App\Services\PrivateFileService;
use App\Services\ReporterService;

/** रिपोर्टर: सूची, प्रोफ़ाइल, बदलना, नवीनीकरण, निलंबन/इस्तीफ़ा, KYC, दस्तावेज़ जारी/रद्द, एक्सपोर्ट */
final class ReporterController extends Controller
{
    public const LABELS = ['designation' => 'पद', 'reporter_type' => 'प्रकार', 'beat_category_id' => 'बीट', 'bureau_id' => 'ब्यूरो', 'area_location_id' => 'क्षेत्र',
        'mobile' => 'मोबाइल', 'address' => 'पता', 'blood_group' => 'ब्लड ग्रुप', 'notes' => 'नोट', 'joining_date' => 'जॉइनिंग', 'valid_until' => 'वैधता', 'user_id' => 'यूज़र'];

    private function find(int $id): array
    {
        return Reporter::find($id) ?? throw new HttpException(404);
    }

    private function where(Request $r): array
    {
        $w = ['u.deleted_at IS NULL'];
        $p = [];
        $f = ['status' => $r->str('status'), 'bureau' => $r->int('bureau'), 'q' => mb_substr($r->str('q'), 0, 100), 'expiring' => $r->bool('expiring')];
        if (isset(Reporter::STATUSES[$f['status']])) {
            $w[] = 'rp.status = ?';
            $p[] = $f['status'];
        }
        if ($f['bureau'] > 0) {
            $w[] = 'rp.bureau_id = ?';
            $p[] = $f['bureau'];
        }
        if ($f['expiring']) {
            $w[] = "rp.status = 'active' AND rp.valid_until <= CURDATE() + INTERVAL 30 DAY";
        }
        if ($f['q'] !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $w[] = '(u.name LIKE ? OR rp.reporter_code LIKE ? OR rp.mobile LIKE ?)';
            array_push($p, $like, $like, $like);
        }
        return [implode(' AND ', $w), $p, $f];
    }

    public function index(Request $request): Response
    {
        [$w, $p, $f] = $this->where($request);
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}reporters rp JOIN {p}users u ON u.id = rp.user_id WHERE $w", $p);
        $items = db()->all(
            "SELECT rp.*, u.name, u.email, b.name AS bureau, d.name AS district,
                    (SELECT COUNT(*) FROM {p}news n WHERE n.reporter_id = rp.user_id AND n.status = 'published' AND n.deleted_at IS NULL) AS published
             FROM {p}reporters rp JOIN {p}users u ON u.id = rp.user_id LEFT JOIN {p}bureaus b ON b.id = rp.bureau_id LEFT JOIN {p}locations d ON d.id = rp.district_id
             WHERE $w ORDER BY rp.id DESC LIMIT 30 OFFSET " . Paginator::offset($page, 30),
            $p
        );
        $counts = array_column(db()->all('SELECT status, COUNT(*) c FROM {p}reporters GROUP BY status'), 'c', 'status');
        $counts[''] = array_sum($counts);
        $counts['expiring'] = (int) db()->value("SELECT COUNT(*) FROM {p}reporters WHERE status = 'active' AND valid_until <= CURDATE() + INTERVAL 30 DAY");
        return $this->view('admin/reporters/index', ['items' => new Paginator($items, $total, 30, $page), 'filters' => $f, 'counts' => $counts,
            'bureaus' => array_column(Bureau::where([], 'name'), 'name', 'id')]);
    }

    public static function profileData(array $r): array
    {
        $user = db()->first('SELECT id, name, email, mobile, avatar, status, last_login_at, last_login_ip FROM {p}users WHERE id = ?', [$r['user_id']]);
        $loc = fn($id) => $id ? db()->value('SELECT name FROM {p}locations WHERE id = ?', [$id]) : null;
        return [
            'r' => $r, 'user' => $user, 'stats' => ReporterService::stats((int) $r['user_id']),
            'bureau' => $r['bureau_id'] ? Bureau::find((int) $r['bureau_id']) : null, 'beat' => $r['beat_category_id'] ? Category::find((int) $r['beat_category_id']) : null,
            'district' => $loc($r['district_id']), 'state' => $loc($r['state_id']), 'area' => $loc($r['area_location_id']),
            'documents' => db()->all('SELECT d.*, u.name AS issuer FROM {p}reporter_documents d LEFT JOIN {p}users u ON u.id = d.issued_by WHERE d.reporter_id = ? ORDER BY d.id DESC', [$r['id']]),
            'recent' => db()->all("SELECT id, title, status, published_at, updated_at, views FROM {p}news WHERE reporter_id = ? AND deleted_at IS NULL ORDER BY updated_at DESC LIMIT 8", [$r['user_id']]),
            'assignments' => db()->all("SELECT id, title, status, deadline, priority FROM {p}assignments WHERE reporter_id = ? ORDER BY id DESC LIMIT 6", [$r['user_id']]),
            'kyc' => (array) json_decode((string) $r['kyc'], true),
        ];
    }

    public function show(Request $request, int $id): Response
    {
        $r = $this->find($id);
        return $this->view('admin/reporters/show', self::profileData($r) + [
            'logins' => db()->all('SELECT * FROM {p}login_history WHERE user_id = ? ORDER BY id DESC LIMIT 10', [$r['user_id']]),
            'passwordLink' => app('session')->getFlash('password_link'),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null);
    }

    private function form(?array $r): Response
    {
        return $this->view('admin/reporters/form', [
            'r' => $r, 'categories' => Category::options(), 'bureaus' => array_column(Bureau::where(['status' => 'active'], 'name'), 'name', 'id'),
            'area' => !empty($r['area_location_id']) ? Location::find((int) $r['area_location_id']) : null,
            'users' => $r ? [] : db()->all("SELECT u.id, u.name, u.email FROM {p}users u LEFT JOIN {p}reporters rp ON rp.user_id = u.id
                JOIN {p}roles ro ON ro.id = u.role_id WHERE rp.id IS NULL AND u.deleted_at IS NULL AND ro.slug <> 'super-admin' ORDER BY u.name"),
        ]);
    }

    /** मौजूदा यूज़र को रिपोर्टर बनाना (बिना आवेदन, जैसे पुराना स्टाफ़) */
    public function store(Request $request): Response
    {
        $d = $this->payload($request, true);
        $user = db()->first('SELECT u.* FROM {p}users u LEFT JOIN {p}reporters rp ON rp.user_id = u.id WHERE u.id = ? AND rp.id IS NULL AND u.deleted_at IS NULL', [$d['user_id']]);
        if (!$user) {
            throw new ValidationException(['user_id' => 'यह यूज़र नहीं मिला या पहले से रिपोर्टर है।'], $request->post());
        }
        $d['mobile'] = $d['mobile'] ?: (string) $user['mobile'];
        if ($d['mobile'] === '') {
            throw new ValidationException(['mobile' => 'मोबाइल ज़रूरी है (सत्यापन इसी से होता है)।'], $request->post());
        }
        $d += ['status' => 'active', 'reporter_code' => 'TMP-' . bin2hex(random_bytes(6)), 'created_by' => auth()->id()];
        $id = Reporter::create($d);
        Reporter::update($id, ['reporter_code' => ReporterService::code($id)]);
        AuditService::log('create', 'reporters', $id, 'रिपोर्टर बनाया: ' . $user['name'] . ' (' . ReporterService::code($id) . ')');
        return $this->toRoute('admin.reporters.show', ['id' => $id])->with('success', $user['name'] . ' अब रिपोर्टर हैं: ' . ReporterService::code($id) . '। रोल बदलना हो तो यूज़र पेज से बदलें।');
    }

    public function edit(Request $request, int $id): Response
    {
        return $this->form($this->find($id));
    }

    public function update(Request $request, int $id): Response
    {
        $r = $this->find($id);
        $d = $this->payload($request, false);
        Reporter::update($id, $d);
        AuditService::log('update', 'reporters', $id, 'रिपोर्टर प्रोफ़ाइल बदली: ' . $r['reporter_code'], $r, $d);
        return $this->toRoute('admin.reporters.show', ['id' => $id])->with('success', 'प्रोफ़ाइल सेव हो गई।');
    }

    private function payload(Request $request, bool $new): array
    {
        $rules = [
            'designation' => 'required|max:100', 'reporter_type' => 'required|in:' . implode(',', array_keys(Reporter::TYPES)),
            'beat_category_id' => 'nullable|integer|exists:categories,id', 'bureau_id' => 'nullable|integer|exists:bureaus,id',
            'area_location_id' => 'nullable|integer|exists:locations,id', 'mobile' => ($new ? 'nullable' : 'required') . '|mobile',
            'address' => 'nullable|max:400', 'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-', 'notes' => 'nullable|max:2000',
        ];
        if ($new) {
            $rules += ['user_id' => 'required|integer', 'joining_date' => 'required|date', 'valid_until' => 'required|date'];
        }
        $v = $this->validate($request, $rules, self::LABELS);
        $area = $v['area_location_id'] ? Location::find((int) $v['area_location_id']) : null;
        // क्षेत्र से ज़िला और राज्य अपने आप
        $district = $state = null;
        if ($area) {
            foreach ([...Location::ancestors($area), $area] as $l) {
                if ($l['type'] === 'state') {
                    $state = (int) $l['id'];
                }
                if ($l['type'] === 'district') {
                    $district = (int) $l['id'];
                }
            }
        }
        $d = [
            'designation' => strip_tags($v['designation']), 'reporter_type' => $v['reporter_type'],
            'beat_category_id' => $v['beat_category_id'] ? (int) $v['beat_category_id'] : null, 'bureau_id' => $v['bureau_id'] ? (int) $v['bureau_id'] : null,
            'area_location_id' => $area ? (int) $area['id'] : null, 'district_id' => $district, 'state_id' => $state,
            'mobile' => $v['mobile'] ? substr(preg_replace('/\D/', '', $v['mobile']), -10) : '', 'address' => $v['address'] ? strip_tags($v['address']) : null,
            'blood_group' => $v['blood_group'], 'notes' => $v['notes'],
        ];
        if ($new) {
            if (strtotime($v['valid_until']) <= strtotime($v['joining_date'])) {
                throw new ValidationException(['valid_until' => 'वैधता जॉइनिंग के बाद की हो।'], $request->post());
            }
            $d += ['user_id' => (int) $v['user_id'], 'joining_date' => date('Y-m-d', strtotime($v['joining_date'])), 'valid_until' => date('Y-m-d', strtotime($v['valid_until']))];
            $photo = db()->value('SELECT avatar FROM {p}users WHERE id = ?', [$d['user_id']]);
            $d['photo'] = $photo ?: null;
        }
        return $d;
    }

    public function renew(Request $request, int $id): Response
    {
        $r = $this->find($id);
        $months = max(1, min(60, $request->int('months', (int) setting('reporter_validity_months', 12))));
        if (in_array($r['status'], ['suspended', 'resigned'], true)) {
            return $this->back()->with('danger', 'निलंबित या इस्तीफ़ा दे चुके रिपोर्टर का नवीनीकरण नहीं होता। पहले दोबारा चालू करें।');
        }
        $until = ReporterService::renew($r, $months);
        AuditService::log('renew', 'reporters', $id, $r['reporter_code'] . " का नवीनीकरण: $months महीने, " . $until . ' तक');
        return $this->back()->with('success', 'वैधता ' . hindi_date($until) . ' तक बढ़ गई। नया ID कार्ड जारी करना न भूलें।');
    }

    /** suspended / resigned / active */
    public function status(Request $request, int $id): Response
    {
        $r = $this->find($id);
        $to = $request->str('status');
        $reason = trim(mb_substr($request->str('reason'), 0, 300));
        if (!in_array($to, ['suspended', 'resigned', 'active'], true) || $to === $r['status']) {
            return $this->back()->with('danger', 'यह स्थिति नहीं चुनी जा सकती।');
        }
        if ($to !== 'active' && $reason === '') {
            return $this->back()->with('danger', 'कारण लिखना ज़रूरी है।');
        }
        if ($to === 'active' && strtotime((string) $r['valid_until']) < strtotime('today')) {
            return $this->back()->with('danger', 'वैधता ख़त्म हो चुकी है। पहले नवीनीकरण करें।');
        }
        ReporterService::setStatus($r, $to, $reason);
        AuditService::log($to === 'active' ? 'activate' : $to, 'reporters', $id, $r['reporter_code'] . ': ' . Reporter::STATUSES[$to][0] . ($reason ? " ($reason)" : ''));
        return $this->back()->with('success', 'स्थिति: ' . Reporter::STATUSES[$to][0] . ($to !== 'active' ? '। लॉगिन बंद और सक्रिय ID कार्ड/अधिकार पत्र रद्द हो गए।' : '। लॉगिन चालू।'));
    }

    public function kyc(Request $request, int $id, string $key): Response
    {
        $r = $this->find($id);
        $kyc = (array) json_decode((string) $r['kyc'], true);
        if (!isset(ReporterApplication::DOCUMENTS[$key], $kyc[$key])) {
            throw new HttpException(404);
        }
        AuditService::log('view_document', 'reporters', $id, 'KYC देखा: ' . ReporterApplication::DOCUMENTS[$key][0] . ' · ' . $r['reporter_code']);
        return PrivateFileService::send($kyc[$key], $r['reporter_code'] . '-' . $key, !$request->bool('download'));
    }

    public function issue(Request $request, int $id): Response
    {
        $r = $this->find($id);
        $type = $request->str('type');
        if (!isset(ReporterDocument::TYPES[$type])) {
            throw new HttpException(422);
        }
        if ($r['status'] !== 'active' && $type !== 'experience') {
            return $this->back()->with('danger', 'निष्क्रिय रिपोर्टर को सिर्फ़ अनुभव प्रमाणपत्र जारी हो सकता है।');
        }
        $doc = ReporterService::issueDocument($r, $type);
        AuditService::log('issue', 'reporters', $id, ReporterDocument::TYPES[$type][0] . ' जारी: ' . $doc['doc_no'] . ' · ' . $r['reporter_code']);
        return $this->redirect(route('admin.reporters.document', ['id' => $id, 'doc' => $doc['id']]));
    }

    public function revoke(Request $request, int $id, int $doc): Response
    {
        $r = $this->find($id);
        $d = db()->first('SELECT * FROM {p}reporter_documents WHERE id = ? AND reporter_id = ?', [$doc, $id]) ?? throw new HttpException(404);
        db()->query("UPDATE {p}reporter_documents SET status = 'revoked', revoked_reason = ? WHERE id = ?", [mb_substr($request->str('reason') ?: 'रद्द किया', 0, 300), $doc]);
        AuditService::log('revoke', 'reporters', $id, $d['doc_no'] . ' रद्द · ' . $r['reporter_code']);
        return $this->back()->with('success', $d['doc_no'] . ' रद्द कर दिया गया।');
    }

    /** दस्तावेज़ का प्रिंट पेज (एडमिन और ख़ुद रिपोर्टर, दोनों के लिए) */
    public static function renderDocument(array $r, array $doc, bool $refresh = false): Response
    {
        $kyc = (array) json_decode((string) $r['kyc'], true);
        $ctx = ['r' => $r, 'doc' => $doc] + self::docContext($r);
        // पत्र/प्रमाणपत्र: एडमिन के टेम्पलेट से (पहली बार बनकर सुरक्षित; $refresh = नए टेम्पलेट से दोबारा)
        $content = $doc['type'] !== 'id_card' ? \App\Services\DocumentTemplateService::forDocument($doc, $ctx, $refresh) : null;
        return new Response(app('view')->render('print/reporter-document', $ctx + [
            'content' => $content, 'canRefresh' => $doc['type'] !== 'id_card' && can('reporters.approve') && auth()->user()['role_slug'] !== 'reporter',
            'signature' => PrivateFileService::dataUri($kyc['signature'] ?? null), 'verifyUrl' => ReporterService::verifyUrl($r),
        ]));
    }

    public function document(Request $request, int $id, int $doc): Response
    {
        $r = $this->find($id);
        $d = db()->first('SELECT * FROM {p}reporter_documents WHERE id = ? AND reporter_id = ?', [$doc, $id]) ?? throw new HttpException(404);
        return self::renderDocument($r, $d);
    }

    /** जारी पत्र को अभी के टेम्पलेट से दोबारा बनाएँ */
    public function refreshDocument(Request $request, int $id, int $doc): Response
    {
        $r = $this->find($id);
        $d = db()->first('SELECT * FROM {p}reporter_documents WHERE id = ? AND reporter_id = ?', [$doc, $id]) ?? throw new HttpException(404);
        if ($d['type'] === 'id_card') {
            throw new HttpException(404);
        }
        \App\Services\DocumentTemplateService::forDocument($d, ['r' => $r, 'doc' => $d] + self::docContext($r), true);
        AuditService::log('update', 'reporters', $id, $d['doc_no'] . ': नए टेम्पलेट से दोबारा बनाया');
        return $this->redirect(route('admin.reporters.document', ['id' => $id, 'doc' => $doc]));
    }

    private static function docContext(array $r): array
    {
        $user = db()->first('SELECT name, email FROM {p}users WHERE id = ?', [$r['user_id']]);
        $loc = fn($id) => $id ? db()->value('SELECT name FROM {p}locations WHERE id = ?', [$id]) : null;
        return ['name' => $user['name'], 'email' => $user['email'], 'district' => $loc($r['district_id']), 'state' => $loc($r['state_id']), 'area' => $loc($r['area_location_id']),
            'bureau' => $r['bureau_id'] ? db()->value('SELECT name FROM {p}bureaus WHERE id = ?', [$r['bureau_id']]) : null,
            'beat' => $r['beat_category_id'] ? db()->value('SELECT name FROM {p}categories WHERE id = ?', [$r['beat_category_id']]) : null,
            'gender' => $r['application_id'] ? db()->value('SELECT gender FROM {p}reporter_applications WHERE id = ?', [$r['application_id']]) : null];
    }

    public function export(Request $request): Response
    {
        [$w, $p] = $this->where($request);
        $rows = db()->all("SELECT rp.reporter_code, u.name, rp.mobile, u.email, rp.designation, rp.reporter_type, d.name AS district, b.name AS bureau, rp.joining_date, rp.valid_until, rp.status
            FROM {p}reporters rp JOIN {p}users u ON u.id = rp.user_id LEFT JOIN {p}bureaus b ON b.id = rp.bureau_id LEFT JOIN {p}locations d ON d.id = rp.district_id WHERE $w ORDER BY rp.id", $p);
        AuditService::log('export', 'reporters', null, count($rows) . ' रिपोर्टर CSV में');
        return Response::csv('reporters-' . date('Y-m-d') . '.csv', ['रिपोर्टर ID', 'नाम', 'मोबाइल', 'ईमेल', 'पद', 'प्रकार', 'ज़िला', 'ब्यूरो', 'जॉइनिंग', 'वैधता', 'स्थिति'],
            array_map(static fn($r) => [$r['reporter_code'], $r['name'], $r['mobile'], $r['email'], $r['designation'], Reporter::TYPES[$r['reporter_type']] ?? $r['reporter_type'], $r['district'], $r['bureau'], $r['joining_date'], $r['valid_until'], Reporter::STATUSES[$r['status']][0]], $rows));
    }
}
