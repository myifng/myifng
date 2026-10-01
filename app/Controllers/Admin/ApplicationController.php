<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\MailTemplate;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Bureau;
use App\Models\Category;
use App\Models\Reporter;
use App\Models\ReporterApplication;
use App\Services\AuditService;
use App\Services\PrivateFileService;
use App\Services\ReporterService;

/** रिपोर्टर आवेदन: सूची, विवरण, दस्तावेज़, टिप्पणी, स्थिति, सौंपना, मंज़ूरी (रिपोर्टर खाता), हटाना */
final class ApplicationController extends Controller
{
    private function find(int $id): array
    {
        return ReporterApplication::find($id) ?? throw new HttpException(404);
    }

    private function filters(Request $r): array
    {
        return ['status' => $r->str('status'), 'q' => mb_substr($r->str('q'), 0, 100), 'district' => $r->int('district'), 'type' => $r->str('type'), 'mine' => $r->bool('mine')];
    }

    private function where(array $f): array
    {
        $w = ['1=1'];
        $p = [];
        if (isset(ReporterApplication::STATUSES[$f['status']])) {
            $w[] = 'a.status = ?';
            $p[] = $f['status'];
        } elseif ($f['status'] === 'open') {
            $w[] = "a.status NOT IN ('approved','rejected')";
        }
        if ($f['q'] !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $w[] = '(a.app_no LIKE ? OR a.full_name LIKE ? OR a.mobile LIKE ? OR a.email LIKE ?)';
            array_push($p, $like, $like, $like, $like);
        }
        if ($f['district'] > 0) {
            $w[] = 'a.district_id = ?';
            $p[] = $f['district'];
        }
        if (isset(Reporter::TYPES[$f['type']])) {
            $w[] = 'a.reporter_type = ?';
            $p[] = $f['type'];
        }
        if ($f['mine']) {
            $w[] = 'a.assigned_to = ?';
            $p[] = auth()->id();
        }
        return [implode(' AND ', $w), $p];
    }

    public function index(Request $request): Response
    {
        $f = $this->filters($request);
        [$w, $p] = $this->where($f);
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}reporter_applications a WHERE $w", $p);
        $items = db()->all(
            "SELECT a.id, a.app_no, a.full_name, a.mobile, a.reporter_type, a.status, a.created_at, a.experience_years, d.name AS district, s.name AS state, u.name AS assignee
             FROM {p}reporter_applications a LEFT JOIN {p}locations d ON d.id = a.district_id LEFT JOIN {p}locations s ON s.id = a.state_id LEFT JOIN {p}users u ON u.id = a.assigned_to
             WHERE $w ORDER BY a.id DESC LIMIT 30 OFFSET " . Paginator::offset($page, 30),
            $p
        );
        $counts = array_column(db()->all('SELECT status, COUNT(*) c FROM {p}reporter_applications GROUP BY status'), 'c', 'status');
        $counts['open'] = array_sum(array_intersect_key($counts, array_flip(['new', 'under_review', 'document_pending', 'verification_pending', 'on_hold'])));
        $counts[''] = array_sum(array_diff_key($counts, ['open' => 1]));
        return $this->view('admin/applications/index', [
            'items' => new Paginator($items, $total, 30, $page), 'filters' => $f, 'counts' => $counts,
            'districts' => db()->all('SELECT DISTINCT l.id, l.name FROM {p}reporter_applications a JOIN {p}locations l ON l.id = a.district_id ORDER BY l.name'),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $a = $this->find($id);
        $loc = fn($lid) => $lid ? db()->value('SELECT name FROM {p}locations WHERE id = ?', [$lid]) : null;
        $photo = PrivateFileService::dataUri(((array) json_decode((string) $a['documents'], true))['photo'] ?? null);
        return $this->view('admin/applications/show', [
            'a' => $a, 'docs' => (array) json_decode((string) $a['documents'], true), 'photo' => $photo,
            'state' => $loc($a['state_id']), 'district' => $loc($a['district_id']),
            'remarks' => db()->all('SELECT r.*, u.name AS user FROM {p}application_remarks r LEFT JOIN {p}users u ON u.id = r.user_id WHERE r.application_id = ? ORDER BY r.id DESC', [$id]),
            'staff' => db()->all("SELECT DISTINCT u.id, u.name FROM {p}users u JOIN {p}roles r ON r.id = u.role_id LEFT JOIN {p}role_permissions rp ON rp.role_id = r.id
                LEFT JOIN {p}permissions p ON p.id = rp.permission_id WHERE u.deleted_at IS NULL AND u.status = 'active' AND (p.name = 'applications.edit' OR r.slug IN ('super-admin','admin')) ORDER BY u.name"),
            'duplicates' => db()->all('SELECT id, app_no, status FROM {p}reporter_applications WHERE id <> ? AND (mobile = ? OR email = ?) ORDER BY id DESC LIMIT 5', [$id, $a['mobile'], $a['email']]),
            'reporter' => $a['reporter_id'] ? Reporter::find((int) $a['reporter_id']) : null,
        ]);
    }

    /** निजी दस्तावेज़ (inline देखना / download) */
    public function document(Request $request, int $id, string $key): Response
    {
        $a = $this->find($id);
        $docs = (array) json_decode((string) $a['documents'], true);
        if (!isset(ReporterApplication::DOCUMENTS[$key], $docs[$key])) {
            throw new HttpException(404);
        }
        AuditService::log('view_document', 'applications', $id, 'दस्तावेज़ देखा: ' . ReporterApplication::DOCUMENTS[$key][0] . ' · ' . $a['app_no']);
        return PrivateFileService::send($docs[$key], $a['app_no'] . '-' . $key, !$request->bool('download'));
    }

    public function remark(Request $request, int $id): Response
    {
        $a = $this->find($id);
        $msg = trim(mb_substr($request->str('message'), 0, 3000));
        if ($msg === '') {
            return $this->back()->with('warning', 'टिप्पणी ख़ाली है।');
        }
        db()->insert('application_remarks', ['application_id' => $id, 'user_id' => auth()->id(), 'type' => 'note', 'message' => $msg, 'created_at' => now()]);
        AuditService::log('remark', 'applications', $id, 'आंतरिक टिप्पणी: ' . $a['app_no']);
        return $this->back()->with('success', 'आंतरिक टिप्पणी जुड़ गई।');
    }

    /** स्थिति: under_review, document_pending (सुधार), verification_pending, on_hold, rejected */
    public function status(Request $request, int $id): Response
    {
        $a = $this->find($id);
        $to = $request->str('status');
        $allowed = ['under_review', 'document_pending', 'verification_pending', 'on_hold', 'rejected'];
        if (!in_array($to, $allowed, true) || $a['status'] === 'approved') {
            return $this->back()->with('danger', 'यह स्थिति नहीं चुनी जा सकती।');
        }
        if ($to === 'rejected') {
            $this->authorize('applications.approve');
        }
        $msg = trim(mb_substr($request->str('message'), 0, 500));
        if (in_array($to, ['document_pending', 'rejected'], true) && $msg === '') {
            return $this->back()->with('danger', 'आवेदक को क्या बताना है, वह संदेश लिखना ज़रूरी है।');
        }
        ReporterService::applicationStatus($a, $to, $msg, in_array($to, ['document_pending', 'on_hold', 'rejected', 'verification_pending'], true));
        if ($assign = $request->int('assigned_to')) {
            db()->query('UPDATE {p}reporter_applications SET assigned_to = ? WHERE id = ?', [$assign, $id]);
        }
        if (in_array($to, ['document_pending', 'rejected'], true)) {
            app('mailer')->send($a['email'], 'आवेदन ' . $a['app_no'] . ': ' . ReporterApplication::STATUSES[$to][0],
                MailTemplate::title('आवेदन की स्थिति: ' . ReporterApplication::STATUSES[$to][0]) . MailTemplate::hello((string) $a['full_name'])
                . MailTemplate::p(e(ReporterApplication::PUBLIC_TEXT[$to])) . ($msg !== '' ? MailTemplate::note(nl2br(e($msg)), $to === 'rejected' ? 'warn' : 'info') : '')
                . MailTemplate::info([['आवेदन संख्या', (string) $a['app_no']], ['स्थिति', ReporterApplication::STATUSES[$to][0]]])
                . MailTemplate::button(route('application.status'), 'आवेदन की स्थिति देखें'));
        }
        AuditService::log('status', 'applications', $id, $a['app_no'] . ': ' . ReporterApplication::STATUSES[$a['status']][0] . ' → ' . ReporterApplication::STATUSES[$to][0]);
        return $this->back()->with('success', 'स्थिति: ' . ReporterApplication::STATUSES[$to][0]);
    }

    public function assign(Request $request, int $id): Response
    {
        $a = $this->find($id);
        $uid = $request->int('assigned_to') ?: null;
        if ($uid && !db()->value("SELECT id FROM {p}users WHERE id = ? AND status = 'active' AND deleted_at IS NULL", [$uid])) {
            return $this->back()->with('danger', 'यूज़र नहीं मिला।');
        }
        db()->query('UPDATE {p}reporter_applications SET assigned_to = ? WHERE id = ?', [$uid, $id]);
        $name = $uid ? (string) db()->value('SELECT name FROM {p}users WHERE id = ?', [$uid]) : 'किसी को नहीं';
        db()->insert('application_remarks', ['application_id' => $id, 'user_id' => auth()->id(), 'type' => 'note', 'message' => 'जाँच सौंपी: ' . $name, 'created_at' => now()]);
        AuditService::log('assign', 'applications', $id, $a['app_no'] . ' की जाँच सौंपी: ' . $name);
        return $this->back()->with('success', 'जाँच ' . $name . ' को सौंपी गई।');
    }

    public function approveForm(Request $request, int $id): Response
    {
        $a = $this->find($id);
        if ($a['status'] === 'approved') {
            return $this->toRoute('admin.applications.show', ['id' => $id])->with('info', 'यह आवेदन पहले ही मंज़ूर है।');
        }
        return $this->view('admin/applications/approve', [
            'a' => $a, 'categories' => Category::options(), 'bureaus' => array_column(Bureau::where(['status' => 'active'], 'name'), 'name', 'id'),
            'emailTaken' => (bool) db()->value('SELECT id FROM {p}users WHERE email = ? AND deleted_at IS NULL', [$a['email']]),
        ]);
    }

    public function approve(Request $request, int $id): Response
    {
        $a = $this->find($id);
        if ($a['status'] === 'approved') {
            throw new HttpException(409, 'यह आवेदन पहले ही मंज़ूर है।');
        }
        $v = $this->validate($request, [
            'designation' => 'required|max:100', 'reporter_type' => 'required|in:' . implode(',', array_keys(Reporter::TYPES)),
            'beat_category_id' => 'nullable|integer|exists:categories,id', 'bureau_id' => 'nullable|integer|exists:bureaus,id',
            'joining_date' => 'required|date', 'valid_until' => 'required|date',
        ], ['designation' => 'पद', 'reporter_type' => 'प्रकार', 'beat_category_id' => 'बीट', 'bureau_id' => 'ब्यूरो', 'joining_date' => 'जॉइनिंग', 'valid_until' => 'वैधता']);
        if (strtotime($v['valid_until']) <= strtotime($v['joining_date'])) {
            throw new ValidationException(['valid_until' => 'वैधता की तारीख़ जॉइनिंग के बाद की हो।'], $request->post());
        }
        if (db()->value('SELECT id FROM {p}users WHERE email = ? AND deleted_at IS NULL', [$a['email']])) {
            throw new ValidationException(['designation' => 'आवेदक के ईमेल (' . $a['email'] . ') से पहले से एक यूज़र खाता है। उस यूज़र को देखें या आवेदक से दूसरा ईमेल लें।'], $request->post());
        }
        $res = ReporterService::approve($a, [
            'designation' => strip_tags($v['designation']), 'reporter_type' => $v['reporter_type'], 'beat_category_id' => $v['beat_category_id'] ? (int) $v['beat_category_id'] : null,
            'bureau_id' => $v['bureau_id'] ? (int) $v['bureau_id'] : null, 'joining_date' => date('Y-m-d', strtotime($v['joining_date'])), 'valid_until' => date('Y-m-d', strtotime($v['valid_until'])),
        ]);
        $r = Reporter::find($res['reporter_id']);
        $sent = app('mailer')->send($a['email'], 'बधाई! आप ' . setting('site_name') . ' के रिपोर्टर बने',
            MailTemplate::title('बधाई! आवेदन मंज़ूर हुआ', '🎉') . MailTemplate::hello((string) $a['full_name'])
            . MailTemplate::p('आप अब <b>' . e((string) setting('site_name')) . '</b> के रिपोर्टर हैं। अपना पासवर्ड बनाकर रिपोर्टर पोर्टल में लॉगिन करें।')
            . MailTemplate::info([['रिपोर्टर ID', (string) $r['reporter_code']], ['लॉगिन ईमेल', (string) $a['email']]])
            . MailTemplate::button($res['link'], 'पासवर्ड बनाएँ')
            . MailTemplate::note('पासवर्ड बनाने का लिंक <b>72 घंटे</b> तक मान्य है।'));
        AuditService::log('approve', 'applications', $id, $a['app_no'] . ' मंज़ूर → ' . $r['reporter_code']);
        \App\Services\NotifyEvents::reporterApproved((string) $a['full_name'], $a['mobile'] ?? null, (string) $r['reporter_code']);
        app('session')->flash('password_link', $res['link']);
        return $this->toRoute('admin.reporters.show', ['id' => $res['reporter_id']])->with($sent ? 'success' : 'warning',
            'रिपोर्टर ' . $r['reporter_code'] . ' बन गया। ' . ($sent ? 'पासवर्ड बनाने का लिंक ईमेल पर भेजा गया।' : 'ईमेल नहीं जा सका: नीचे दिया लिंक रिपोर्टर को ख़ुद भेजें।'));
    }

    /** सिर्फ़ अस्वीकार/होल्ड वाले; निजी दस्तावेज़ भी हटते हैं */
    public function destroy(Request $request, int $id): Response
    {
        $a = $this->find($id);
        if (!in_array($a['status'], ['rejected', 'on_hold'], true)) {
            return $this->back()->with('danger', 'सिर्फ़ अस्वीकार या होल्ड वाले आवेदन हटाए जा सकते हैं।');
        }
        foreach ((array) json_decode((string) $a['documents'], true) as $p) {
            if ($abs = PrivateFileService::absolute($p)) {
                @unlink($abs);
            }
        }
        ReporterApplication::delete($id);
        AuditService::log('delete', 'applications', $id, 'आवेदन और उसके दस्तावेज़ हटाए: ' . $a['app_no'], ['app_no' => $a['app_no'], 'name' => $a['full_name']]);
        return $this->toRoute('admin.applications.index')->with('success', 'आवेदन ' . $a['app_no'] . ' और उसके दस्तावेज़ हटा दिए गए।');
    }

    public function export(Request $request): Response
    {
        [$w, $p] = $this->where($this->filters($request));
        $rows = db()->all("SELECT a.app_no, a.full_name, a.mobile, a.email, a.reporter_type, s.name AS state, d.name AS district, a.experience_years, a.status, a.created_at
            FROM {p}reporter_applications a LEFT JOIN {p}locations d ON d.id = a.district_id LEFT JOIN {p}locations s ON s.id = a.state_id WHERE $w ORDER BY a.id DESC LIMIT 5000", $p);
        AuditService::log('export', 'applications', null, count($rows) . ' आवेदन CSV में');
        return Response::csv('applications-' . date('Y-m-d') . '.csv', ['आवेदन संख्या', 'नाम', 'मोबाइल', 'ईमेल', 'प्रकार', 'राज्य', 'ज़िला', 'अनुभव', 'स्थिति', 'तारीख़'],
            array_map(static fn($r) => [$r['app_no'], $r['full_name'], $r['mobile'], $r['email'], Reporter::TYPES[$r['reporter_type']] ?? $r['reporter_type'], $r['state'], $r['district'], $r['experience_years'], ReporterApplication::STATUSES[$r['status']][0], $r['created_at']], $rows));
    }
}
