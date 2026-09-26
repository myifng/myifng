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

/** लॉगिन, लॉगआउट, पासवर्ड भूलें और रीसेट */
final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return $this->view('auth/login');
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, ['email' => 'required|email', 'password' => 'required'], ['email' => 'ईमेल', 'password' => 'पासवर्ड']);
        $result = auth()->attempt($data['email'], (string) $request->input('password'), $request);
        if (!$result['ok']) {
            return $this->toRoute('admin.login')->withErrors(['email' => $result['message']])->withInput(['email' => $data['email']]);
        }
        AuditService::log('login', 'auth', auth()->id(), 'लॉगिन किया');
        $intended = (string) app('session')->get('intended', '');
        app('session')->forget('intended');
        $target = $intended !== '' && str_starts_with($intended, '/') && !str_starts_with($intended, '//') ? $intended : route('admin.dashboard');
        return $this->redirect($target)->with('success', 'स्वागत है, ' . user('name') . '!');
    }

    public function logout(Request $request): Response
    {
        if ($id = auth()->id()) {
            AuditService::log('logout', 'auth', $id, 'लॉगआउट किया');
            db()->insert('login_history', ['user_id' => $id, 'ip' => $request->ip(), 'user_agent' => $request->userAgent(), 'status' => 'logout']);
        }
        auth()->logout();
        return $this->toRoute('admin.login')->with('success', 'आप सुरक्षित रूप से लॉगआउट हो गए।');
    }

    public function showForgot(Request $request): Response
    {
        return $this->view('auth/forgot');
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
            $link = route('admin.password.reset', ['token' => $token]);
            $site = e(setting('site_name', 'News'));
            app('mailer')->send($user['email'], "$site: पासवर्ड रीसेट", "<p>नमस्ते " . e($user['name']) . ",</p><p>पासवर्ड बदलने के लिए नीचे दिए लिंक पर जाएँ। यह लिंक 60 मिनट तक चलेगा।</p><p><a href=\"" . e($link) . "\">" . e($link) . "</a></p><p>अगर आपने यह अनुरोध नहीं किया, तो इस ईमेल को अनदेखा करें।</p>");
            AuditService::log('password_reset_requested', 'auth', $user['id'], 'पासवर्ड रीसेट लिंक माँगा');
        }
        return $this->back()->with('success', 'अगर यह ईमेल हमारे सिस्टम में है, तो पासवर्ड रीसेट का लिंक भेज दिया गया है। अपना इनबॉक्स (और स्पैम) देखें।');
    }

    public function showReset(Request $request, string $token): Response
    {
        if (!$this->findToken($token)) {
            return $this->toRoute('admin.password.forgot')->with('danger', 'यह लिंक ग़लत है या इसकी समय-सीमा ख़त्म हो गई। नया लिंक माँगें।');
        }
        return $this->view('auth/reset', ['token' => $token]);
    }

    public function reset(Request $request): Response
    {
        $token = $request->str('token');
        $row = preg_match('/^[a-f0-9]{64}$/', $token) ? $this->findToken($token) : null;
        if (!$row) {
            return $this->toRoute('admin.password.forgot')->with('danger', 'यह लिंक ग़लत है या इसकी समय-सीमा ख़त्म हो गई। नया लिंक माँगें।');
        }
        $this->validate($request, ['password' => 'required|password|confirmed'], ['password' => 'नया पासवर्ड']);
        User::update((int) $row['user_id'], ['password' => password_hash((string) $request->input('password'), PASSWORD_DEFAULT)]);
        db()->update('password_resets', ['used_at' => now()], 'user_id = ? AND used_at IS NULL', [$row['user_id']]);
        db()->delete('login_attempts', 'email = (SELECT email FROM {p}users WHERE id = ?)', [$row['user_id']]);
        AuditService::log('password_reset', 'auth', $row['user_id'], 'पासवर्ड रीसेट किया');
        return $this->toRoute('admin.login')->with('success', 'पासवर्ड बदल गया। अब नए पासवर्ड से लॉगिन करें।');
    }

    private function findToken(string $token): ?array
    {
        return db()->first('SELECT * FROM {p}password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1', [hash('sha256', $token)]);
    }
}
