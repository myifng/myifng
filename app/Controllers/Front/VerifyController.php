<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;
use App\Services\ReporterService;

/** सार्वजनिक सत्यापन: /verify-reporter (ID + मोबाइल) और ID कार्ड का QR (/verify-reporter/{code}/{token}) */
final class VerifyController extends FrontController
{
    public function form(Request $request): Response
    {
        return $this->show(null, null);
    }

    public function check(Request $request): Response
    {
        $code = strtoupper(trim($request->str('code')));
        $mobile = substr(preg_replace('/\D/', '', $request->str('mobile')), -10);
        $r = preg_match('/^[A-Z]{2,8}-\d{4}-\d{4,}$/', $code) && strlen($mobile) === 10
            ? db()->first('SELECT * FROM {p}reporters WHERE reporter_code = ? AND mobile = ?', [$code, $mobile]) : null;
        return $this->show($r, $r ? null : 'इस रिपोर्टर ID और मोबाइल नंबर से कोई रिपोर्टर नहीं मिला। ID कार्ड पर लिखी ID ध्यान से डालें।', $code);
    }

    public function qr(Request $request, string $code, string $token): Response
    {
        $code = strtoupper($code);
        $valid = hash_equals(ReporterService::token($code), strtolower($token));
        $r = $valid ? db()->first('SELECT * FROM {p}reporters WHERE reporter_code = ?', [$code]) : null;
        return $this->show($r, $r ? null : 'यह QR कोड मान्य नहीं है। कार्ड नकली हो सकता है।', $code);
    }

    private function show(?array $r, ?string $error, string $code = ''): Response
    {
        $info = null;
        if ($r) {
            $valid = $r['status'] === 'active' && strtotime((string) $r['valid_until']) >= strtotime('today');
            $info = [
                'valid' => $valid, 'name' => db()->value('SELECT name FROM {p}users WHERE id = ?', [$r['user_id']]), 'photo' => $r['photo'],
                'code' => $r['reporter_code'], 'designation' => $r['designation'],
                'district' => $r['district_id'] ? db()->value('SELECT name FROM {p}locations WHERE id = ?', [$r['district_id']]) : null,
                'state' => $r['state_id'] ? db()->value('SELECT name FROM {p}locations WHERE id = ?', [$r['state_id']]) : null,
                'joining' => $r['joining_date'], 'valid_until' => $r['valid_until'], 'status' => $r['status'],
                'mobile' => \App\Services\OtpService::maskMobile((string) $r['mobile']),
                'card' => db()->value("SELECT doc_no FROM {p}reporter_documents WHERE reporter_id = ? AND type = 'id_card' AND status = 'active' ORDER BY id DESC LIMIT 1", [$r['id']]),
            ];
        }
        return $this->view('front/verify', ['info' => $info, 'error' => $error, 'code' => $code,
            'seo' => ['title' => 'रिपोर्टर सत्यापन', 'description' => setting('site_name') . ' के रिपोर्टर की पहचान जाँचें।', 'robots' => $r ? 'noindex,nofollow' : 'index,follow', 'canonical' => route('verify')]]);
    }
}
