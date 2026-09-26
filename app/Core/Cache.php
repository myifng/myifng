<?php
declare(strict_types=1);

namespace App\Core;

/**
 * फ़ाइल कैश (storage/cache)। shared hosting पर Redis/Memcached की ज़रूरत नहीं।
 *   cache()->remember('menu.main', 600, fn() => ...);
 */
final class Cache
{
    public function __construct(private string $dir, private bool $enabled = true)
    {
    }

    private function path(string $key): string
    {
        $group = str_contains($key, '.') ? preg_replace('/[^a-z0-9_-]/i', '', strstr($key, '.', true)) : 'misc';
        return $this->dir . '/' . $group . '/' . sha1($key) . '.cache';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->enabled) {
            return $default;
        }
        $file = $this->path($key);
        if (!is_file($file)) {
            return $default;
        }
        $data = @unserialize((string) file_get_contents($file), ['allowed_classes' => false]);
        if (!is_array($data) || ($data['e'] !== 0 && $data['e'] < time())) {
            @unlink($file);
            return $default;
        }
        return $data['v'];
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        if (!$this->enabled) {
            return;
        }
        $file = $this->path($key);
        if (!is_dir(dirname($file)) && !@mkdir(dirname($file), 0755, true)) {
            return;
        }
        $tmp = $file . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, serialize(['e' => $ttl > 0 ? time() + $ttl : 0, 'v' => $value]), LOCK_EX) !== false) {
            @rename($tmp, $file);
        }
    }

    public function remember(string $key, int $ttl, callable $fn): mixed
    {
        $v = $this->get($key, $miss = new \stdClass());
        if ($v !== $miss) {
            return $v;
        }
        $v = $fn();
        $this->set($key, $v, $ttl);
        return $v;
    }

    public function forget(string $key): void
    {
        @unlink($this->path($key));
    }

    /** पूरा समूह हटाएँ: flush('menu') या सब: flush() */
    public function flush(?string $group = null): void
    {
        $dirs = $group ? [$this->dir . '/' . $group] : (glob($this->dir . '/*', GLOB_ONLYDIR) ?: []);
        foreach ($dirs as $d) {
            foreach (glob($d . '/*.cache') ?: [] as $f) {
                @unlink($f);
            }
        }
    }
}
