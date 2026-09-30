<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Models\Reader;

/**
 * पाठक (वेबसाइट यूज़र) का लॉगिन। स्टाफ़ के Auth से पूरी तरह अलग: अलग टेबल, अलग सत्र कुंजी।
 */
final class ReaderAuth
{
    private const KEY = 'reader_id';
    private static ?array $current = null;
    private static bool $loaded = false;

    public static function enabled(): bool
    {
        return setting('readers_enabled', '1') === '1';
    }

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = (int) app('session')->get(self::KEY, 0);
            if ($id && self::enabled()) {
                $r = Reader::find($id);
                self::$current = $r && $r['status'] === 'active' ? $r : null;
                if (!self::$current) {
                    app('session')->forget(self::KEY);
                }
            }
        }
        return self::$current;
    }

    public static function id(): ?int
    {
        return self::user() ? (int) self::$current['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function login(array $reader): void
    {
        app('session')->regenerate();
        app('session')->set(self::KEY, (int) $reader['id']);
        Reader::update((int) $reader['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        self::$current = $reader;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        app('session')->forget(self::KEY);
        app('session')->regenerate();
        self::$current = null;
    }

    /** [reader|null, त्रुटि] — 10 ग़लत प्रयास पर 15 मिनट रोक (ईमेल+IP) */
    public static function attempt(string $email, string $password, Request $request): array
    {
        $email = mb_strtolower(trim($email));
        $key = 'throttle.reader.' . sha1($email . '|' . $request->ip());
        $fails = (int) cache()->get($key, 0);
        if ($fails >= 10) {
            return [null, 'बहुत ज़्यादा ग़लत प्रयास। 15 मिनट बाद कोशिश करें।'];
        }
        $r = db()->first('SELECT * FROM {p}readers WHERE email = ?', [$email]);
        if (!$r || !password_verify($password, $r['password'])) {
            cache()->set($key, $fails + 1, 900);
            return [null, 'ईमेल या पासवर्ड ग़लत है।'];
        }
        if ($r['status'] === 'blocked') {
            return [null, 'यह खाता बंद कर दिया गया है।'];
        }
        if ($r['status'] === 'pending') {
            return [null, 'पहले ईमेल पर भेजे लिंक से खाता सत्यापित करें। लिंक दोबारा चाहिए तो "लिंक दोबारा भेजें" दबाएँ।'];
        }
        cache()->forget($key);
        if (password_needs_rehash($r['password'], PASSWORD_DEFAULT)) {
            Reader::update((int) $r['id'], ['password' => password_hash($password, PASSWORD_DEFAULT)]);
        }
        return [$r, null];
    }

    /** एक बार का टोकन (सिर्फ़ हैश सेव) */
    public static function token(int $readerId, string $type, int $minutes): string
    {
        db()->query('DELETE FROM {p}reader_tokens WHERE reader_id = ? AND type = ?', [$readerId, $type]);
        $raw = bin2hex(random_bytes(24));
        db()->insert('reader_tokens', ['reader_id' => $readerId, 'type' => $type, 'token_hash' => hash('sha256', $raw), 'expires_at' => date('Y-m-d H:i:s', time() + $minutes * 60)]);
        return $raw;
    }

    /** टोकन से पाठक (और टोकन ख़त्म) */
    public static function consume(string $raw, string $type, bool $delete = true): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $raw)) {
            return null;
        }
        $t = db()->first('SELECT * FROM {p}reader_tokens WHERE token_hash = ? AND type = ? AND expires_at > NOW()', [hash('sha256', $raw), $type]);
        if (!$t) {
            return null;
        }
        if ($delete) {
            db()->query('DELETE FROM {p}reader_tokens WHERE id = ?', [$t['id']]);
        }
        return Reader::find((int) $t['reader_id']);
    }

    /** IP का हैश (असली IP सेव नहीं) */
    public static function ipHash(Request $request): string
    {
        return hash_hmac('sha256', 'ip|' . $request->ip(), (string) config('app.key'));
    }
}
