<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Reporter;
use App\Models\ReporterApplication;
use App\Services\OtpService;
use App\Services\PrivateFileService;
use App\Services\ReporterService;

/** रिपोर्टर बनें (/join-as-reporter) और आवेदन की स्थिति (/application-status, OTP से) */
final class ReporterJoinController extends FrontController
{
    private const LABELS = [
        'full_name' => 'पूरा नाम', 'guardian_name' => 'पिता/पति का नाम', 'dob' => 'जन्मतिथि', 'gender' => 'लिंग', 'mobile' => 'मोबाइल', 'whatsapp' => 'WhatsApp',
        'email' => 'ईमेल', 'address' => 'पूरा पता', 'state_id' => 'राज्य', 'district_id' => 'ज़िला', 'city' => 'शहर/क़स्बा', 'pincode' => 'पिनकोड',
        'reporter_type' => 'रिपोर्टर का प्रकार', 'experience_years' => 'अनुभव (साल)', 'previous_org' => 'पिछला संस्थान', 'education' => 'शिक्षा',
        'languages' => 'भाषाएँ', 'about' => 'अपने बारे में', 'declaration' => 'घोषणा',
    ];

    private function enabled(): void
    {
        if (setting('join_enabled', '1') !== '1') {
            throw new HttpException(404);
        }
    }

    public function form(Request $request): Response
    {
        $this->enabled();
        $states = db()->all("SELECT id, name FROM {p}locations WHERE type = 'state' AND status = 'active' ORDER BY is_popular DESC, name");
        // हर ज़िले का राज्य (बीच में मंडल हो सकता है)
        $districts = db()->all("SELECT d.id, d.name, CASE WHEN p.type = 'state' THEN p.id ELSE p.parent_id END AS state_id
            FROM {p}locations d JOIN {p}locations p ON p.id = d.parent_id WHERE d.type = 'district' AND d.status = 'active' ORDER BY d.name");
        return $this->view('front/join', [
            'states' => $states, 'districts' => $districts, 'startedAt' => time(),
            'seo' => ['title' => 'रिपोर्टर बनें', 'description' => setting('site_name') . ' के साथ रिपोर्टर के रूप में जुड़ें। ऑनलाइन आवेदन करें।', 'canonical' => route('join')],
        ]);
    }

    public function submit(Request $request): Response
    {
        $this->enabled();
        // बॉट: छिपा खाना भरा हो या फ़ॉर्म 15 सेकंड से कम में भरा
        if ($request->str('website') !== '' || (time() - $request->int('started_at')) < 15) {
            throw new ValidationException(['full_name' => 'फ़ॉर्म जमा नहीं हो सका। कृपया दोबारा ध्यान से भरें।'], $request->post());
        }
        $v = $this->validate($request, [
            'full_name' => 'required|max:120', 'guardian_name' => 'required|max:120', 'dob' => 'required|date', 'gender' => 'required|in:male,female,other',
            'mobile' => 'required|mobile', 'whatsapp' => 'nullable|mobile', 'email' => 'required|email|max:190', 'address' => 'required|max:400',
            'state_id' => 'required|integer', 'district_id' => 'required|integer', 'city' => 'nullable|max:120', 'pincode' => 'required|regex:/^[1-9][0-9]{5}$/',
            'reporter_type' => 'required|in:' . implode(',', array_keys(Reporter::TYPES)), 'experience_years' => 'required|integer|min:0|max:60',
            'previous_org' => 'nullable|max:190', 'education' => 'required|max:150', 'languages' => 'nullable|max:150', 'about' => 'nullable|max:2000',
            'declaration' => 'accepted',
        ], self::LABELS);
        $errors = [];
        $age = (int) date_diff(date_create($v['dob']), date_create('today'))->y;
        if ($age < 18 || $age > 80 || strtotime($v['dob']) > time()) {
            $errors['dob'] = 'आवेदक की उम्र 18 से 80 साल के बीच होनी चाहिए।';
        }
        $district = db()->first("SELECT id, parent_id FROM {p}locations WHERE id = ? AND type = 'district' AND status = 'active'", [(int) $v['district_id']]);
        $state = db()->first("SELECT id FROM {p}locations WHERE id = ? AND type = 'state' AND status = 'active'", [(int) $v['state_id']]);
        if (!$state) {
            $errors['state_id'] = 'राज्य चुनें।';
        } elseif (!$district || !$this->within((int) $district['id'], (int) $state['id'])) {
            $errors['district_id'] = 'चुने गए राज्य का ज़िला चुनें।';
        }
        $mobile = substr(preg_replace('/\D/', '', $v['mobile']), -10);
        if (db()->value("SELECT id FROM {p}reporter_applications WHERE mobile = ? AND status NOT IN ('approved','rejected')", [$mobile])) {
            $errors['mobile'] = 'इस मोबाइल नंबर से एक आवेदन पहले से प्रक्रिया में है। “आवेदन की स्थिति” पेज पर देखें।';
        }
        if (db()->value("SELECT id FROM {p}users WHERE email = ? AND deleted_at IS NULL", [mb_strtolower($v['email'])])) {
            $errors['email'] = 'यह ईमेल पहले से किसी खाते में है। दूसरा ईमेल दें।';
        }
        // दस्तावेज़: पहले सब जाँचें, फिर सेव
        $files = [];
        foreach (ReporterApplication::DOCUMENTS as $key => [$label, $required]) {
            $f = $request->file('doc_' . $key);
            if (!$f) {
                if ($required) {
                    $errors['doc_' . $key] = "$label अपलोड करें।";
                }
                continue;
            }
            $files[$key] = $f;
        }
        if ($errors) {
            throw new ValidationException($errors + ['_files' => 'सुरक्षा के लिए अपलोड की गई फ़ाइलें दोबारा चुननी होंगी।'], $request->post());
        }
        $dir = 'applications/' . date('Y') . '/' . bin2hex(random_bytes(8));
        $stored = [];
        foreach ($files as $key => $f) {
            $isImage = in_array($key, ['photo', 'signature'], true);
            $r = PrivateFileService::store($f, $dir, $isImage ? ['image/jpeg', 'image/png'] : ['image/jpeg', 'image/png', 'application/pdf'], $isImage ? 2 : 5);
            if (!$r['ok']) {
                foreach ($stored as $p) {
                    @unlink((string) PrivateFileService::absolute($p));
                }
                throw new ValidationException(['doc_' . $key => ReporterApplication::DOCUMENTS[$key][0] . ': ' . $r['error'], '_files' => 'सुरक्षा के लिए अपलोड की गई फ़ाइलें दोबारा चुननी होंगी।'], $request->post());
            }
            $stored[$key] = $r['path'];
        }
        $id = ReporterApplication::create([
            'app_no' => 'TMP-' . bin2hex(random_bytes(6)), 'full_name' => strip_tags($v['full_name']), 'guardian_name' => strip_tags($v['guardian_name']), 'dob' => date('Y-m-d', strtotime($v['dob'])),
            'gender' => $v['gender'], 'mobile' => $mobile, 'whatsapp' => $v['whatsapp'] ? substr(preg_replace('/\D/', '', $v['whatsapp']), -10) : null,
            'email' => mb_strtolower($v['email']), 'address' => strip_tags($v['address']), 'state_id' => (int) $state['id'], 'district_id' => (int) $district['id'],
            'city' => $v['city'] ? strip_tags($v['city']) : null, 'pincode' => $v['pincode'], 'preferred_area_id' => (int) $district['id'],
            'reporter_type' => $v['reporter_type'], 'experience_years' => (int) $v['experience_years'], 'previous_org' => $v['previous_org'] ? strip_tags($v['previous_org']) : null,
            'education' => strip_tags($v['education']), 'languages' => $v['languages'] ? strip_tags($v['languages']) : null, 'about' => $v['about'] ? strip_tags($v['about']) : null,
            'documents' => json_encode($stored), 'status' => 'new', 'ip' => $request->ip(), 'consent_at' => now(),
        ]);
        $appNo = ReporterService::appNo($id);
        ReporterApplication::update($id, ['app_no' => $appNo]);
        app('mailer')->send(mb_strtolower($v['email']), 'आवेदन मिल गया: ' . $appNo,
            '<p>नमस्ते ' . e($v['full_name']) . ',</p><p>' . e(setting('site_name')) . ' में रिपोर्टर के लिए आपका आवेदन मिल गया है।</p><p><b>आवेदन संख्या: ' . e($appNo) . '</b></p><p>स्थिति देखें: ' . e(route('application.status')) . '</p>');
        app('session')->flash('joined_app', $appNo);
        return $this->toRoute('join.done');
    }

    /** ज़िला उसी राज्य का है (बीच में मंडल हो सकता है) */
    private function within(int $locId, int $stateId): bool
    {
        $pid = $locId;
        for ($i = 0; $i < 6 && $pid; $i++) {
            $pid = (int) db()->value('SELECT parent_id FROM {p}locations WHERE id = ?', [$pid]);
            if ($pid === $stateId) {
                return true;
            }
        }
        return false;
    }

    public function done(Request $request): Response
    {
        $appNo = app('session')->getFlash('joined_app');
        if (!$appNo) {
            return $this->toRoute('join');
        }
        return $this->view('front/join-done', ['appNo' => $appNo, 'seo' => ['title' => 'आवेदन जमा हो गया', 'robots' => 'noindex,nofollow']]);
    }

    // ---------- स्थिति ----------

    public function status(Request $request): Response
    {
        $s = app('session');
        $view = (array) $s->get('app_status', []);
        $app = null;
        if (!empty($view['id']) && ($view['until'] ?? 0) > time()) {
            $app = ReporterApplication::find((int) $view['id']);
        }
        $pending = (array) $s->get('app_otp', []);
        return $this->view('front/application-status', [
            'app' => $app, 'step' => $app ? 'result' : (!empty($pending['id']) && ($pending['until'] ?? 0) > time() ? 'otp' : 'lookup'),
            'maskedEmail' => $pending['masked'] ?? '', 'debugOtp' => config('app.debug') ? $s->getFlash('debug_otp') : null,
            'seo' => ['title' => 'रिपोर्टर आवेदन की स्थिति', 'robots' => 'noindex,nofollow'],
        ]);
    }

    public function lookup(Request $request): Response
    {
        $appNo = strtoupper(trim($request->str('app_no')));
        $mobile = substr(preg_replace('/\D/', '', $request->str('mobile')), -10);
        $app = preg_match('/^RPT-APP-\d{4}-\d{6}$/', $appNo) && strlen($mobile) === 10
            ? db()->first('SELECT * FROM {p}reporter_applications WHERE app_no = ? AND mobile = ?', [$appNo, $mobile]) : null;
        if (!$app) {
            // कौन-सा हिस्सा ग़लत है, यह नहीं बताते
            return $this->toRoute('application.status')->with('danger', 'आवेदन संख्या और मोबाइल नंबर मेल नहीं खाते।');
        }
        $otp = OtpService::create('app_status', $appNo, $request->ip());
        if (!$otp['ok']) {
            return $this->toRoute('application.status')->with('danger', $otp['error']);
        }
        $sent = app('mailer')->send($app['email'], 'सत्यापन कोड: ' . $otp['code'], '<p>आपके आवेदन ' . e($appNo) . ' की स्थिति देखने का कोड: <b style="font-size:22px">' . $otp['code'] . '</b></p><p>यह 10 मिनट तक मान्य है। किसी से साझा न करें।</p>');
        $s = app('session');
        $s->set('app_otp', ['id' => (int) $app['id'], 'app_no' => $appNo, 'until' => time() + OtpService::TTL, 'masked' => OtpService::maskEmail($app['email'])]);
        if (config('app.debug')) {
            $s->flash('debug_otp', $otp['code']);
        }
        return $this->toRoute('application.status')->with($sent ? 'success' : 'warning', $sent
            ? 'कोड आपके ईमेल ' . OtpService::maskEmail($app['email']) . ' पर भेजा गया है।'
            : 'कोड ईमेल पर नहीं भेजा जा सका। थोड़ी देर बाद कोशिश करें या हमसे संपर्क करें।');
    }

    public function verifyOtp(Request $request): Response
    {
        $s = app('session');
        $p = (array) $s->get('app_otp', []);
        if (empty($p['id']) || ($p['until'] ?? 0) < time()) {
            $s->forget('app_otp');
            return $this->toRoute('application.status')->with('danger', 'समय ख़त्म हो गया। दोबारा आवेदन संख्या डालें।');
        }
        $ok = OtpService::verify('app_status', (string) $p['app_no'], $request->str('otp'));
        if ($ok !== true) {
            return $this->toRoute('application.status')->with('danger', (string) $ok);
        }
        $s->forget('app_otp');
        $s->set('app_status', ['id' => $p['id'], 'until' => time() + 900]);
        return $this->toRoute('application.status');
    }

    public function reset(Request $request): Response
    {
        app('session')->forget('app_status');
        app('session')->forget('app_otp');
        return $this->toRoute('application.status');
    }
}
