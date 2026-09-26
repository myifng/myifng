<?php
declare(strict_types=1);

namespace App\Services;

/** एक बार वाला कोड (OTP): 6 अंक, hashed, 10 मिनट, 5 कोशिश; 15 मिनट में 3 से ज़्यादा नहीं */
final class OtpService
{
    public const TTL = 600;
    public const MAX_ATTEMPTS = 5;

    /** @return array{ok: bool, code?: string, error?: string} */
    public static function create(string $purpose, string $identifier, string $ip): array
    {
        $recent = (int) db()->value('SELECT COUNT(*) FROM {p}otp_codes WHERE purpose = ? AND identifier = ? AND created_at > NOW() - INTERVAL 15 MINUTE', [$purpose, $identifier]);
        if ($recent >= 3) {
            return ['ok' => false, 'error' => 'बहुत बार कोड माँगा गया। 15 मिनट बाद कोशिश करें।'];
        }
        $code = (string) random_int(100000, 999999);
        db()->insert('otp_codes', ['purpose' => $purpose, 'identifier' => $identifier, 'code_hash' => password_hash($code, PASSWORD_DEFAULT),
            'expires_at' => date('Y-m-d H:i:s', time() + self::TTL), 'ip' => $ip, 'created_at' => now()]);
        return ['ok' => true, 'code' => $code];
    }

    /** सही हो तो true (और कोड ख़त्म) */
    public static function verify(string $purpose, string $identifier, string $code): bool|string
    {
        $row = db()->first('SELECT * FROM {p}otp_codes WHERE purpose = ? AND identifier = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1', [$purpose, $identifier]);
        if (!$row) {
            return 'कोड की समय-सीमा ख़त्म हो गई। नया कोड माँगें।';
        }
        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            return 'बहुत बार ग़लत कोड डाला गया। नया कोड माँगें।';
        }
        if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, $row['code_hash'])) {
            db()->query('UPDATE {p}otp_codes SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
            return 'कोड सही नहीं है।';
        }
        db()->query('DELETE FROM {p}otp_codes WHERE purpose = ? AND identifier = ?', [$purpose, $identifier]);
        return true;
    }

    /** पुराने कोड साफ़ */
    public static function prune(): void
    {
        db()->query('DELETE FROM {p}otp_codes WHERE expires_at < NOW() - INTERVAL 1 DAY');
    }

    /** a****@gmail.com */
    public static function maskEmail(string $email): string
    {
        [$u, $d] = array_pad(explode('@', $email, 2), 2, '');
        return mb_substr($u, 0, 1) . str_repeat('*', max(3, mb_strlen($u) - 1)) . '@' . $d;
    }

    public static function maskMobile(string $m): string
    {
        $d = preg_replace('/\D/', '', $m);
        return strlen($d) >= 4 ? str_repeat('x', strlen($d) - 4) . substr($d, -4) : 'xxxx';
    }
}
