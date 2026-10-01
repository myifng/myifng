<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Str;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\AuditService;
use App\Services\TwoFactorService;

/** लॉगिन, लॉगआउट, पासवर्ड भूलें और रीसेट */
final class AuthController extends Controller
{
    /** रिपोर्टर पोर्टल (/reporter/…) या एडमिन */
    private function portal(Request $request): string
    {
        return str_starts_with('/' . ltrim($request->path(), '/'), '/reporter/') ? 'reporter' : 'admin';
    }

    /** इस पोर्टल का रूट नाम */
    private function rn(Request $request, string $name): string
    {
        return $this->portal($request) . '.' . $name;
    }

    /** अलग-अलग लॉगिन चालू है? (सेटिंग → सुरक्षा) */
    public static function separated(): bool
    {
        return setting('separate_reporter_login', '1') === '1';
    }

    /** पोर्टल याद रखें: सेशन ख़त्म होने/लॉगआउट पर सही लॉगिन पेज */
    private static function rememberPortal(string $portal): void
    {
        if (!headers_sent()) {
            setcookie('np_portal', $portal, ['expires' => time() + 180 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
        }
    }

    public function showLogin(Request $request): Response
    {
        return $this->view('auth/login', ['portal' => $this->portal($request)]);
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, ['email' => 'required|email', 'password' => 'required'], ['email' => 'ईमेल', 'password' => 'पासवर्ड']);
        $portal = $this->portal($request);
        // पासवर्ड जाँचें, पर लॉगिन अभी नहीं (पोर्टल, IP और OTP के बाद)
        $result = auth()->attempt($data['email'], (string) $request->input('password'), $request, false);
        if (!$result['ok']) {
            return $this->toRoute($portal . '.login')->withErrors(['email' => $result['message']])->withInput(['email' => $data['email']]);
        }
        $user = $result['user'];
        $role = (string) db()->value('SELECT slug FROM {p}roles WHERE id = ?', [$user['role_id']]);
        // रिपोर्टर सिर्फ़ /reporter/login से, बाकी स्टाफ़ सिर्फ़ एडमिन लॉगिन से (पासवर्ड सही होने के बाद ही बताया जाता है)
        $isReporter = $role === 'reporter';
        if (self::separated() && $isReporter !== ($portal === 'reporter')) {
            AuditService::log('login_wrong_portal', 'auth', (int) $user['id'], 'ग़लत लॉगिन पेज से प्रयास (' . $portal . '): ' . $user['email']);
            $msg = $isReporter ? 'आप रिपोर्टर हैं: कृपया रिपोर्टर लॉगिन पेज से लॉगिन करें: ' . route('reporter.login')
                : 'यह पेज सिर्फ़ रिपोर्टर के लिए है। स्टाफ़ अपने एडमिन लॉगिन पेज से लॉगिन करें।';
            return $this->toRoute($portal . '.login')->withErrors(['email' => $msg])->withInput(['email' => $data['email']]);
        }
        if (!\App\Services\SecurityService::staffIpOk($request->ip(), $role)) {
            AuditService::log('ip_blocked', 'auth', (int) $user['id'], 'allowlist से बाहर के IP से लॉगिन: ' . $request->ip() . ' (' . $user['email'] . ')');
            return $this->toRoute($portal . '.login')->withErrors(['email' => 'इस नेटवर्क (IP) से एडमिन लॉगिन की अनुमति नहीं है। दफ़्तर के नेटवर्क से लॉगिन करें।'])->withInput(['email' => $data['email']]);
        }
        // दो-चरण लॉगिन: भरोसेमंद डिवाइस न हो तो ईमेल OTP
        if (TwoFactorService::enabled() && !TwoFactorService::trusted((int) $user['id'])) {
            $r = TwoFactorService::start($user, $portal, $request);
            if (!$r['ok']) {
                AuditService::log('otp_mail_failed', 'auth', (int) $user['id'], 'OTP ईमेल नहीं गया: ' . $user['email']);
                return $this->toRoute($portal . '.login')->withErrors(['email' => $r['message']])->withInput(['email' => $data['email']]);
            }
            return $this->toRoute($portal . '.login.otp');
        }
        return $this->complete($request, $user, $portal);
    }

    /** लॉगिन पूरा: सेशन, पोर्टल याद, ऑडिट, सही पेज पर */
    private function complete(Request $request, array $user, string $portal, string $how = ''): Response
    {
        auth()->login($user, $request);
        $isReporter = (auth()->user()['role_slug'] ?? '') === 'reporter';
        self::rememberPortal($isReporter ? 'reporter' : 'admin');
        AuditService::log('login', 'auth', auth()->id(), 'लॉगिन किया' . ($portal === 'reporter' ? ' (रिपोर्टर पोर्टल)' : '') . $how);
        $intended = (string) app('session')->get('intended', '');
        app('session')->forget('intended');
        $target = $intended !== '' && str_starts_with($intended, '/') && !str_starts_with($intended, '//') ? $intended : route('admin.dashboard');
        return $this->redirect($target)->with('success', 'स्वागत है, ' . user('name') . '!');
    }

    // ---------- दो-चरण लॉगिन (ईमेल OTP) ----------
    public function showOtp(Request $request): Response
    {
        $p = TwoFactorService::pending($request);
        $portal = $this->portal($request);
        if (!$p) {
            return $this->toRoute($portal . '.login')->with('warning', 'OTP का समय ख़त्म हो गया या लॉगिन शुरू नहीं हुआ। दोबारा लॉगिन करें।');
        }
        $email = (string) db()->value('SELECT email FROM {p}users WHERE id = ?', [$p['uid']]);
        return $this->view('auth/otp', ['portal' => $portal, 'email' => TwoFactorService::maskEmail($email),
            'wait' => max(0, $p['sent'] + TwoFactorService::RESEND_AFTER - time()), 'expires' => $p['exp'], 'days' => TwoFactorService::rememberDays()]);
    }

    public function verifyOtp(Request $request): Response
    {
        $portal = $this->portal($request);
        $r = TwoFactorService::verify($request, $request->str('otp'));
        if (!$r['ok']) {
            return $this->toRoute($portal . ($r['restart'] ? '.login' : '.login.otp'))->withErrors([$r['restart'] ? 'email' : 'otp' => $r['message']]);
        }
        $user = $r['user'];
        if ($request->bool('remember_device')) {
            TwoFactorService::trust((int) $user['id'], $request);
        }
        return $this->complete($request, $user, $portal, ' (ईमेल OTP से)');
    }

    public function resendOtp(Request $request): Response
    {
        $portal = $this->portal($request);
        $r = TwoFactorService::resend($request);
        if (!empty($r['restart'])) {
            return $this->toRoute($portal . '.login')->withErrors(['email' => $r['message']]);
        }
        return $this->toRoute($portal . '.login.otp')->with($r['ok'] ? 'success' : 'warning', $r['message']);
    }

    public function logout(Request $request): Response
    {
        $to = (auth()->user()['role_slug'] ?? '') === 'reporter' && self::separated() ? 'reporter.login' : 'admin.login';
        if ($id = auth()->id()) {
            AuditService::log('logout', 'auth', $id, 'लॉगआउट किया');
            db()->insert('login_history', ['user_id' => $id, 'ip' => $request->ip(), 'user_agent' => $request->userAgent(), 'status' => 'logout']);
        }
        auth()->logout();
        return $this->toRoute($to)->with('success', 'आप सुरक्षित रूप से लॉगआउट हो गए।');
    }

    public function showForgot(Request $request): Response
    {
        return $this->view('auth/forgot', ['portal' => $this->portal($request)]);
    }

    /** रीसेट लिंक भेजें। ईमेल मौजूद हो या नहीं, संदेश एक जैसा (ताकि कोई ईमेल की जाँच न कर सके) */
    public function sendReset(Request $request): Response
    {
        $data = $this->validate($request, ['email' => 'required|email'], ['email' => 'ईमेल']);
        $user = User::findByEmail($data['email']);
        if ($user && $user['status'] === 'active') {
            $token = Str::random(32);
            db()->update('password_resets', ['used_at' => now()], 'user_id = ? AND used_at IS NULL', [$user['id']]);
            PasswordReset::create(['user_id' => $user['id'], 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 3600)]);
            $link = route($this->rn($request, 'password.reset'), ['token' => $token]);
            $site = e(setting('site_name', 'News'));
            app('mailer')->send($user['email'], "$site: पासवर्ड रीसेट", "<p>नमस्ते " . e($user['name']) . ",</p><p>पासवर्ड बदलने के लिए नीचे दिए लिंक पर जाएँ। यह लिंक 60 मिनट तक चलेगा।</p><p><a href=\"" . e($link) . "\">" . e($link) . "</a></p><p>अगर आपने यह अनुरोध नहीं किया, तो इस ईमेल को अनदेखा करें।</p>");
            AuditService::log('password_reset_requested', 'auth', $user['id'], 'पासवर्ड रीसेट लिंक माँगा');
        }
        return $this->back()->with('success', 'अगर यह ईमेल हमारे सिस्टम में है, तो पासवर्ड रीसेट का लिंक भेज दिया गया है। अपना इनबॉक्स (और स्पैम) देखें।');
    }

    public function showReset(Request $request, string $token): Response
    {
        if (!$this->findToken($token)) {
            return $this->toRoute($this->rn($request, 'password.forgot'))->with('danger', 'यह लिंक ग़लत है या इसकी समय-सीमा ख़त्म हो गई। नया लिंक माँगें।');
        }
        return $this->view('auth/reset', ['token' => $token, 'portal' => $this->portal($request)]);
    }

    public function reset(Request $request): Response
    {
        $token = $request->str('token');
        $row = preg_match('/^[a-f0-9]{64}$/', $token) ? $this->findToken($token) : null;
        if (!$row) {
            return $this->toRoute($this->rn($request, 'password.forgot'))->with('danger', 'यह लिंक ग़लत है या इसकी समय-सीमा ख़त्म हो गई। नया लिंक माँगें।');
        }
        $this->validate($request, ['password' => 'required|password|confirmed'], ['password' => 'नया पासवर्ड']);
        User::update((int) $row['user_id'], ['password' => password_hash((string) $request->input('password'), PASSWORD_DEFAULT)]);
        TwoFactorService::forget((int) $row['user_id']); // नया पासवर्ड = पुराने भरोसेमंद डिवाइस रद्द
        db()->update('password_resets', ['used_at' => now()], 'user_id = ? AND used_at IS NULL', [$row['user_id']]);
        db()->delete('login_attempts', 'email = (SELECT email FROM {p}users WHERE id = ?)', [$row['user_id']]);
        AuditService::log('password_reset', 'auth', $row['user_id'], 'पासवर्ड रीसेट किया');
        return $this->toRoute($this->rn($request, 'login'))->with('success', 'पासवर्ड बदल गया। अब नए पासवर्ड से लॉगिन करें।');
    }

    private function findToken(string $token): ?array
    {
        return db()->first('SELECT * FROM {p}password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1', [hash('sha256', $token)]);
    }
}
