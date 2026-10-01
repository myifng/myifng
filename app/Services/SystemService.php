<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** सिस्टम (§70): सर्वर की सेहत, कैश, सफ़ाई, धीमे अनुरोध */
final class SystemService
{
    /** कैश के हिस्से => लेबल */
    public const CACHE_GROUPS = ['home' => 'होमपेज के सेक्शन', 'menus' => 'मेनू', 'settings' => 'सेटिंग', 'trending' => 'ट्रेंडिंग/लोकप्रिय', 'sitemap' => 'साइटमैप',
        'analytics' => 'एनालिटिक्स', 'locations' => 'लोकेशन', 'election' => 'चुनाव टैली', 'redirects' => 'रीडायरेक्ट', 'seo' => 'SEO'];

    public const TASKS = ['logs' => 'पुरानी लॉग फ़ाइलें', 'sessions' => 'पुराने सेशन', 'attempts' => 'पुराने लॉगिन प्रयास (1 दिन से पुराने)',
        'outbox' => 'भेजी जा चुकी सूचनाएँ (30 दिन)', 'notfound' => 'हल हो चुके 404 (90 दिन)', 'audit' => 'पुराने ऑडिट लॉग (सेटिंग के दिन)', 'optimize' => 'टेबल optimize'];

    /** सर्वर की सेहत: [समूह => [[नाम, मान, ठीक?, सलाह]]] */
    public static function health(): array
    {
        $ini = static fn(string $k) => (string) ini_get($k);
        $bytes = static function (string $v): int {
            $n = (int) $v;
            return match (strtolower(substr(trim($v), -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
        };
        $ext = [];
        foreach (['pdo_mysql' => true, 'mbstring' => true, 'openssl' => true, 'fileinfo' => true, 'gd' => true, 'zip' => false, 'zlib' => true, 'intl' => false, 'curl' => false, 'Zend OPcache' => false] as $e => $req) {
            $ok = extension_loaded($e);
            $ext[] = [$e, $ok ? 'है' : 'नहीं', $ok || !$req, $req ? 'ज़रूरी' : 'बेहतर प्रदर्शन/सुविधा के लिए'];
        }
        $dirs = [];
        foreach (['storage/cache', 'storage/logs', 'storage/sessions', 'storage/private', 'storage/backups', 'public/uploads'] as $d) {
            $ok = is_dir(BASE_PATH . '/' . $d) && is_writable(BASE_PATH . '/' . $d);
            $dirs[] = [$d, $ok ? 'लिखने लायक' : 'लिख नहीं सकते', $ok, 'फ़ोल्डर की अनुमति 755/775 रखें'];
        }
        $dbv = (string) db()->value('SELECT VERSION()');
        $dbSize = (int) db()->value('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE ?', [str_replace('_', '\\_', db()->prefix()) . '%']);
        $tables = (int) db()->value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE ?', [str_replace('_', '\\_', db()->prefix()) . '%']);
        $pending = count(DashboardService::pendingMigrations());
        $last = cache()->get('system.scheduler_last');
        $free = @disk_free_space(BASE_PATH) ?: 0;
        return [
            'PHP' => [
                ['PHP वर्ज़न', PHP_VERSION, PHP_VERSION_ID >= 80100, '8.1 या नया ज़रूरी'],
                ['memory_limit', $ini('memory_limit'), $ini('memory_limit') === '-1' || $bytes($ini('memory_limit')) >= 128 * 1048576, 'कम से कम 128M'],
                ['upload_max_filesize', $ini('upload_max_filesize'), $bytes($ini('upload_max_filesize')) >= 8 * 1048576, 'वीडियो/ई-पेपर के लिए 32M+ बेहतर'],
                ['post_max_size', $ini('post_max_size'), $bytes($ini('post_max_size')) >= $bytes($ini('upload_max_filesize')), 'upload_max_filesize से बड़ा हो'],
                ['max_execution_time', $ini('max_execution_time') . ' सेकंड', (int) $ini('max_execution_time') === 0 || (int) $ini('max_execution_time') >= 30, '30+ (बैकअप के लिए 120+)'],
            ],
            'Extensions' => $ext,
            'फ़ोल्डर' => $dirs,
            'डेटाबेस और ऐप' => [
                ['डेटाबेस', $dbv, true, ''],
                ['टेबल / आकार', $tables . ' / ' . BackupService::size($dbSize), true, ''],
                ['ऐप वर्ज़न', config('app.version') . ' (Phase ' . config('app.phase') . ')', true, ''],
                ['बाकी अपडेट (migration)', (string) $pending, $pending === 0, 'डैशबोर्ड पर "अभी अपडेट करें" दबाएँ'],
                ['शेड्यूलर आख़िरी बार चला', $last ? date('d-m-Y H:i', (int) $last) : 'जानकारी नहीं', $last && time() - (int) $last < 3 * 3600, 'साइट पर ट्रैफ़िक से चलता है; चाहें तो cron से /robots.txt हर 15 मिनट खोलें'],
                ['डिस्क में ख़ाली', BackupService::size((int) $free), $free > 500 * 1048576, 'कम से कम 500 MB ख़ाली रखें'],
                ['कैश', config('app.cache') ? 'चालू' : 'बंद', (bool) config('app.cache'), 'config/env.php में CACHE = true'],
                // Phase 16: PWA को HTTPS चाहिए (localhost छोड़कर); आइकन बनाने के लिए GD
                ['PWA (ऐप)', PwaService::enabled() ? 'चालू' . (function_exists('imagecreatetruecolor') ? '' : ' (GD नहीं: आइकन नहीं बनेंगे)') : 'बंद',
                    !PwaService::enabled() || (function_exists('imagecreatetruecolor') && (str_starts_with((string) config('app.url'), 'https://') || in_array(parse_url((string) config('app.url'), PHP_URL_HOST), ['localhost', '127.0.0.1'], true))),
                    'फ़ोन पर इंस्टॉल और ऑफ़लाइन के लिए HTTPS (SSL) ज़रूरी है'],
            ],
        ];
    }

    /** कैश के हर हिस्से की फ़ाइलें/आकार */
    public static function cacheStats(): array
    {
        $dir = BASE_PATH . '/storage/cache';
        $out = [];
        foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $d) {
            $g = basename($d);
            $n = 0;
            $sz = 0;
            foreach (glob($d . '/*.cache') ?: [] as $f) {
                $n++;
                $sz += (int) @filesize($f);
            }
            $out[$g] = ['group' => $g, 'label' => self::CACHE_GROUPS[$g] ?? $g, 'files' => $n, 'size' => $sz];
        }
        uasort($out, static fn($a, $b) => $b['size'] <=> $a['size']);
        return array_values($out);
    }

    public static function clearCache(?string $group): int
    {
        $dir = BASE_PATH . '/storage/cache';
        $n = 0;
        $dirs = $group ? [$dir . '/' . basename($group)] : (glob($dir . '/*', GLOB_ONLYDIR) ?: []);
        foreach ($dirs as $d) {
            foreach (glob($d . '/*.cache') ?: [] as $f) {
                $n += @unlink($f) ? 1 : 0;
            }
        }
        return $n;
    }

    public static function opcache(): ?array
    {
        if (!function_exists('opcache_get_status')) {
            return null;
        }
        $s = @opcache_get_status(false);
        if (!is_array($s)) {
            return null;
        }
        return ['enabled' => (bool) ($s['opcache_enabled'] ?? false), 'hit' => round((float) ($s['opcache_statistics']['opcache_hit_rate'] ?? 0), 1),
            'scripts' => (int) ($s['opcache_statistics']['num_cached_scripts'] ?? 0), 'mem' => (int) ($s['memory_usage']['used_memory'] ?? 0)];
    }

    /** एक सफ़ाई का काम; लौटाए: कितना हटा */
    public static function cleanup(string $task): int
    {
        $n = 0;
        switch ($task) {
            case 'logs':
                $days = max(3, (int) setting('log_retention_days', '30'));
                foreach (glob(BASE_PATH . '/storage/logs/*.log') ?: [] as $f) {
                    if (filemtime($f) < time() - $days * 86400) {
                        $n += @unlink($f) ? 1 : 0;
                    }
                }
                break;
            case 'sessions':
                $life = max(1440, (int) ini_get('session.gc_maxlifetime'));
                foreach (glob(BASE_PATH . '/storage/sessions/sess_*') ?: [] as $f) {
                    if (filemtime($f) < time() - $life) {
                        $n += @unlink($f) ? 1 : 0;
                    }
                }
                break;
            case 'attempts':
                $n = db()->query('DELETE FROM {p}login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY')->rowCount();
                break;
            case 'outbox':
                $n = db()->query("DELETE FROM {p}notification_outbox WHERE status IN ('sent','failed','skipped') AND created_at < NOW() - INTERVAL 30 DAY")->rowCount();
                break;
            case 'notfound':
                $n = db()->query("DELETE FROM {p}not_found_log WHERE status <> 'new' AND last_seen < NOW() - INTERVAL 90 DAY")->rowCount();
                break;
            case 'audit':
                $days = (int) setting('audit_retention_days', '365');
                if ($days > 0) {
                    $n = db()->query('DELETE FROM {p}audit_logs WHERE created_at < NOW() - INTERVAL ? DAY', [$days])->rowCount();
                    db()->query('DELETE FROM {p}login_history WHERE created_at < NOW() - INTERVAL ? DAY', [$days]);
                }
                break;
            case 'optimize':
                foreach (db()->all('SELECT table_name t FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE ? AND engine IS NOT NULL', [str_replace('_', '\\_', db()->prefix()) . '%']) as $t) {
                    db()->pdo()->query('OPTIMIZE TABLE `' . str_replace('`', '', $t['t']) . '`')->fetchAll();
                    $n++;
                }
                break;
        }
        return $n;
    }

    /** शेड्यूलर से (दिन में एक बार): हल्की सफ़ाई; optimize नहीं */
    public static function autoClean(): void
    {
        if (cache()->get('system.autoclean') !== null) {
            return;
        }
        cache()->set('system.autoclean', time(), 86400);
        foreach (['logs', 'sessions', 'attempts', 'outbox', 'notfound', 'audit'] as $t) {
            try {
                self::cleanup($t);
            } catch (\Throwable $e) {
                logger()->warning("AutoClean $t: " . $e->getMessage());
            }
        }
    }

    // ---------- धीमे अनुरोध ----------
    public static function slow(float $ms, string $method, string $path): void
    {
        if ($ms < max(200, (int) setting('slow_request_ms', '1500'))) {
            return;
        }
        $line = json_encode(['t' => date('Y-m-d H:i:s'), 'm' => $method, 'p' => mb_substr($path, 0, 200), 'ms' => (int) $ms, 'q' => Database::$queries,
            'qms' => (int) Database::$queryMs, 'mem' => (int) round(memory_get_peak_usage(true) / 1048576)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @file_put_contents(BASE_PATH . '/storage/logs/slow-' . date('Y-m-d') . '.log', $line . "\n", FILE_APPEND | LOCK_EX);
    }

    public static function slowLog(int $limit = 20): array
    {
        $files = glob(BASE_PATH . '/storage/logs/slow-*.log') ?: [];
        rsort($files);
        $out = [];
        foreach (array_slice($files, 0, 7) as $f) {
            foreach (array_reverse(file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) as $l) {
                if (($j = json_decode($l, true)) && count($out) < $limit) {
                    $out[] = $j;
                }
            }
        }
        return $out;
    }
}
