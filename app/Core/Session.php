<?php
declare(strict_types=1);

namespace App\Core;

/**
 * सुरक्षित सेशन: storage/sessions में, HttpOnly + SameSite कुकी।
 * flash() का डेटा सिर्फ़ अगले अनुरोध तक रहता है।
 */
final class Session
{
    public function start(string $savePath, bool $secure, string $name = 'nsess'): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '86400');
        session_name($name);
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
        session_start();

        // पिछले अनुरोध का flash हटाएँ, इस अनुरोध के लिए तैयार करें
        $_SESSION['_flash_old'] = $_SESSION['_flash_new'] ?? [];
        $_SESSION['_flash_new'] = [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_new'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash_new'][$key] ?? $_SESSION['_flash_old'][$key] ?? $default;
    }

    /** अभी के अनुरोध में भी दिखे (रीडायरेक्ट के बिना) */
    public function now(string $key, mixed $value): void
    {
        $_SESSION['_flash_old'][$key] = $value;
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        }
        session_destroy();
    }
}
