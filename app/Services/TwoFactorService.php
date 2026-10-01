<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Request;

/**
 * दो-चरण लॉगिन: पासवर्ड सही होने के बाद ईमेल पर 6 अंकों का OTP।
 * OTP सेशन में सिर्फ़ HMAC के रूप में (सादा कभी नहीं); 5 ग़लत कोशिश पर रद्द, दोबारा भेजना 60 सेकंड बाद (अधिकतम 5)।
 * "इस डिवाइस को याद रखें": बेतरतीब टोकन कुकी में, डेटाबेस में उसका SHA-256।
 */
final class TwoFactorService
{
    private const KEY = '2fa_pending';
    public const COOKIE = 'np_td';
    public const MAX_TRIES = 5;
    public const MAX_SENDS = 5;
    public const RESEND_AFTER = 60;

    public static function enabled(): bool
    {
        return setting('two_factor_enabled', '0') === '1' && empty(config('app.two_factor_bypass'));
    }

    public static function rememberDays(): int
    {
        return max(0, min(90, (int) setting('two_factor_remember_days', '30')));
    }

    private static function expiry(): int
    {
        return max(3, min(30, (int) setting('two_factor_expiry', '10'))) * 60;
    }

    private static function hmac(string $code, int $uid): string
    {
        return hash_hmac('sha256', $uid . '|' . $code, (string) config('app.key') . '|2fa');
    }

    /** यह ब्राउज़र इस यूज़र के लिए भरोसेमंद है? */
    public static function trusted(int $uid): bool
    {
        $tok = (string) ($_COOKIE[self::COOKIE] ?? '');
        if (self::rememberDays() === 0 || !preg_match('/^[a-f0-9]{64}$/', $tok)) {
            return false;
        }
        $row = db()->first('SELECT id FROM {p}trusted_devices WHERE token_hash = ? AND user_id = ? AND expires_at > NOW()', [hash('sha256', $tok), $uid]);
        if ($row) {
            db()->update('trusted_devices', ['last_used_at' => now()], 'id = ?', [$row['id']]);
        }
        return (bool) $row;
    }

    /** OTP बनाएँ, सेशन में रखें, ईमेल भेजें: ['ok' => bool, 'message' => string] */
    public static function start(array $user, string $portal, Request $req): array
    {
        $code = (string) random_int(100000, 999999);
        app('session')->regenerate();
        app('session')->set(self::KEY, [
            'uid' => (int) $user['id'], 'portal' => $portal, 'hash' => self::hmac($code, (int) $user['id']),
            'exp' => time() + self::expiry(), 'tries' => 0, 'sends' => 1, 'sent' => time(),
            'fp' => hash('sha256', $req->userAgent()),
        ]);
        if (!self::mail($user, $code, $req)) {
            app('session')->forget(self::KEY);
            return ['ok' => false, 'message' => 'OTP ईमेल नहीं भेजा जा सका, इसलिए अभी लॉगिन नहीं हो सकता। एडमिन से ईमेल (SMTP) सेटिंग जाँचने को कहें।'];
        }
        self::history((int) $user['id'], $req, 'otp_sent');
        return ['ok' => true, 'message' => ''];
    }

    /** चल रहा OTP (समय ख़त्म या दूसरा ब्राउज़र = null) */
    public static function pending(Request $req): ?array
    {
        $p = app('session')->get(self::KEY);
        if (!is_array($p) || empty($p['uid'])) {
            return null;
        }
        if ($p['exp'] < time() || !hash_equals((string) $p['fp'], hash('sha256', $req->userAgent()))) {
            app('session')->forget(self::KEY);
            return null;
        }
        return $p;
    }

    public static function clear(): void
    {
        app('session')->forget(self::KEY);
    }

    /** ['ok' => bool, 'user' => ?array, 'message' => string, 'restart' => bool] */
    public static function verify(Request $req, string $code): array
    {
        $p = self::pending($req);
        if (!$p) {
            return ['ok' => false, 'user' => null, 'message' => 'OTP का समय ख़त्म हो गया। दोबारा लॉगिन करें।', 'restart' => true];
        }
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) === 6 && hash_equals($p['hash'], self::hmac($code, (int) $p['uid']))) {
            self::clear();
            $user = db()->first("SELECT * FROM {p}users WHERE id = ? AND deleted_at IS NULL AND status = 'active'", [$p['uid']]);
            return $user ? ['ok' => true, 'user' => $user, 'message' => '', 'restart' => false]
                : ['ok' => false, 'user' => null, 'message' => 'खाता चालू नहीं है।', 'restart' => true];
        }
        $p['tries']++;
        $email = (string) db()->value('SELECT email FROM {p}users WHERE id = ?', [$p['uid']]);
        // ग़लत OTP भी लॉगिन-सीमा में गिना जाए (पासवर्ड जानने वाला अंदाज़े से OTP न तोड़ सके)
        db()->insert('login_attempts', ['ip' => $req->ip(), 'email' => mb_substr($email, 0, 190)]);
        self::history((int) $p['uid'], $req, 'otp_failed');
        if ($p['tries'] >= self::MAX_TRIES) {
            self::clear();
            return ['ok' => false, 'user' => null, 'message' => 'बहुत बार ग़लत OTP डाला गया। सुरक्षा के लिए दोबारा पासवर्ड से लॉगिन करें।', 'restart' => true];
        }
        app('session')->set(self::KEY, $p);
        $left = self::MAX_TRIES - $p['tries'];
        return ['ok' => false, 'user' => null, 'message' => "OTP ग़लत है। ($left कोशिश बाकी)", 'restart' => false];
    }

    /** OTP दोबारा भेजें (नया कोड; पुराना बेकार) */
    public static function resend(Request $req): array
    {
        $p = self::pending($req);
        if (!$p) {
            return ['ok' => false, 'message' => 'OTP का समय ख़त्म हो गया। दोबारा लॉगिन करें।', 'restart' => true];
        }
        $wait = $p['sent'] + self::RESEND_AFTER - time();
        if ($wait > 0) {
            return ['ok' => false, 'message' => "नया OTP $wait सेकंड बाद माँग सकते हैं।", 'restart' => false];
        }
        if ($p['sends'] >= self::MAX_SENDS) {
            return ['ok' => false, 'message' => 'बहुत बार OTP भेजा जा चुका है। थोड़ी देर बाद दोबारा लॉगिन करें।', 'restart' => false];
        }
        $user = db()->first('SELECT * FROM {p}users WHERE id = ?', [$p['uid']]);
        $code = (string) random_int(100000, 999999);
        $p = ['hash' => self::hmac($code, (int) $p['uid']), 'exp' => time() + self::expiry(), 'sends' => $p['sends'] + 1, 'sent' => time(), 'tries' => 0] + $p;
        app('session')->set(self::KEY, $p);
        if (!$user || !self::mail($user, $code, $req)) {
            return ['ok' => false, 'message' => 'OTP ईमेल नहीं भेजा जा सका। थोड़ी देर बाद कोशिश करें।', 'restart' => false];
        }
        self::history((int) $p['uid'], $req, 'otp_sent');
        return ['ok' => true, 'message' => 'नया OTP भेज दिया गया। पुराना OTP अब नहीं चलेगा।', 'restart' => false];
    }

    /** "इस डिवाइस को याद रखें" */
    public static function trust(int $uid, Request $req): void
    {
        $days = self::rememberDays();
        if ($days === 0 || headers_sent()) {
            return;
        }
        $tok = bin2hex(random_bytes(32));
        db()->insert('trusted_devices', ['user_id' => $uid, 'token_hash' => hash('sha256', $tok), 'user_agent' => mb_substr($req->userAgent(), 0, 255),
            'ip' => $req->ip(), 'expires_at' => date('Y-m-d H:i:s', time() + $days * 86400), 'last_used_at' => now()]);
        setcookie(self::COOKIE, $tok, ['expires' => time() + $days * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
        // पुराने/ख़त्म डिवाइस की सफ़ाई
        db()->query('DELETE FROM {p}trusted_devices WHERE expires_at < NOW()');
    }

    /** इस यूज़र के सभी भरोसेमंद डिवाइस भूलें (पासवर्ड बदलने/रीसेट पर, या प्रोफ़ाइल से) */
    public static function forget(int $uid): int
    {
        return db()->query('DELETE FROM {p}trusted_devices WHERE user_id = ?', [$uid])->rowCount();
    }

    public static function devices(int $uid): int
    {
        return (int) db()->value('SELECT COUNT(*) FROM {p}trusted_devices WHERE user_id = ? AND expires_at > NOW()', [$uid]);
    }

    public static function maskEmail(string $email): string
    {
        [$u, $d] = array_pad(explode('@', $email, 2), 2, '');
        return mb_substr($u, 0, min(2, mb_strlen($u))) . str_repeat('•', max(2, mb_strlen($u) - 2)) . '@' . $d;
    }

    private static function mail(array $user, string $code, Request $req): bool
    {
        $site = (string) setting('site_name');
        $min = (int) (self::expiry() / 60);
        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#1c1b1d;max-width:480px">'
            . '<p>नमस्ते ' . e((string) $user['name']) . ',</p>'
            . '<p><b>' . e($site) . '</b> में लॉगिन के लिए आपका OTP:</p>'
            . '<p style="font-size:32px;font-weight:700;letter-spacing:8px;background:#f4f3f3;border-radius:8px;padding:12px 16px;text-align:center;margin:12px 0">' . e($code) . '</p>'
            . '<p>यह OTP <b>' . $min . ' मिनट</b> तक मान्य है। इसे किसी से साझा न करें; हमारी टीम कभी OTP नहीं माँगती।</p>'
            . '<p style="color:#666;font-size:13px">IP: ' . e($req->ip()) . ' · डिवाइस: ' . e(function_exists('device_name') ? device_name($req->userAgent()) : mb_substr($req->userAgent(), 0, 80)) . ' · समय: ' . date('d-m-Y H:i') . '</p>'
            . '<p style="color:#b3121a;font-size:13px">अगर आपने लॉगिन की कोशिश नहीं की, तो तुरंत अपना पासवर्ड बदलें।</p></div>';
        return app('mailer')->send((string) $user['email'], "$code: $site लॉगिन OTP", $html);
    }

    private static function history(int $uid, Request $req, string $status): void
    {
        db()->insert('login_history', ['user_id' => $uid, 'ip' => $req->ip(), 'user_agent' => mb_substr($req->userAgent(), 0, 255), 'status' => $status]);
    }
}
