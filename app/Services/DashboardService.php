<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Migrator;

/** डैशबोर्ड: विजेट (config/dashboard.php), चार्ट डेटा, सेटअप चेकलिस्ट, सिस्टम अपडेट */
final class DashboardService
{
    /** दिखने लायक कार्ड, मान के साथ */
    public static function cards(): array
    {
        $phase = (int) config('app.phase');
        $out = [];
        foreach ((array) config('dashboard.cards', []) as $key => $c) {
            $modPhase = (int) (config('modules.modules.' . $c['module'] . '.phase') ?? 99);
            if ($modPhase > $phase || !can($c['permission']) || !method_exists(self::class, $c['provider'])) {
                continue;
            }
            $data = self::{$c['provider']}();
            $out[$key] = $c + $data + ['link' => ($c['route'] && app('router')->has($c['route'])) ? route($c['route']) : null];
            if (count($out) >= (int) config('dashboard.max_cards', 8)) {
                break;
            }
        }
        return $out;
    }

    /* ---------- कार्ड के मान ---------- */

    private static function users(): array
    {
        $r = db()->first("SELECT COUNT(*) total, SUM(status = 'active') active FROM {p}users WHERE deleted_at IS NULL");
        return ['value' => (int) $r['total'], 'sub' => 'चालू: ' . num($r['active'])];
    }

    private static function pages(): array
    {
        $r = db()->first("SELECT SUM(status = 'published') pub, SUM(status = 'draft') draft FROM {p}pages WHERE deleted_at IS NULL");
        return ['value' => (int) $r['pub'], 'sub' => 'प्रकाशित · ड्राफ़्ट: ' . num($r['draft'])];
    }

    private static function homeSections(): array
    {
        $r = db()->first('SELECT COUNT(*) total, SUM(is_active) active FROM {p}home_sections');
        return ['value' => (int) $r['active'], 'sub' => 'चालू · कुल ' . num($r['total'])];
    }

    private static function loginsToday(): array
    {
        return ['value' => (int) db()->value("SELECT COUNT(*) FROM {p}login_history WHERE status = 'success' AND created_at >= CURDATE()"), 'sub' => 'सफल लॉगिन'];
    }

    private static function failedLogins(): array
    {
        $n = (int) db()->value("SELECT COUNT(*) FROM {p}login_history WHERE status IN ('failed','blocked') AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")
            + (int) db()->value('SELECT COUNT(*) FROM {p}login_attempts WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)');
        return ['value' => $n, 'sub' => $n > 10 ? 'ध्यान दें: ज़्यादा प्रयास' : 'सामान्य', 'alert' => $n > 10];
    }

    /* ---------- चार्ट ---------- */

    /** पिछले N दिन: [labels, series1, series2] */
    public static function daily(string $sql, int $days = 14): array
    {
        $rows = array_column(db()->all($sql, [$days - 1]), null, 'd');
        $out = ['labels' => [], 'a' => [], 'b' => []];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $out['labels'][] = (int) date('j', strtotime($d)) . ' ' . mb_substr(HINDI_MONTHS[(int) date('n', strtotime($d)) - 1], 0, 3);
            $out['a'][] = (int) ($rows[$d]['a'] ?? 0);
            $out['b'][] = (int) ($rows[$d]['b'] ?? 0);
        }
        return $out;
    }

    public static function loginChart(): array
    {
        return self::daily("SELECT DATE(created_at) d, SUM(status = 'success') a, SUM(status IN ('failed','blocked')) b FROM {p}login_history WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(created_at)");
    }

    public static function activityChart(): array
    {
        return self::daily('SELECT DATE(created_at) d, COUNT(*) a, 0 b FROM {p}audit_logs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(created_at)');
    }

    /* ---------- सेटअप चेकलिस्ट ---------- */

    /** [label, done, link, hint] */
    public static function checklist(): array
    {
        if (!can('settings.view')) {
            return [];
        }
        $s = static fn(string $tab) => route('admin.settings', ['tab' => $tab]);
        $https = str_starts_with((string) config('app.url'), 'https://');
        return [
            ['लोगो अपलोड करें', setting('logo') !== '', $s('branding'), 'साइट का लोगो और फ़ेविकॉन'],
            ['फ़ेविकॉन लगाएँ', setting('favicon') !== '', $s('branding'), 'ब्राउज़र टैब का आइकन'],
            ['संपर्क जानकारी भरें', setting('contact_email') !== '' && setting('contact_phone') !== '', $s('contact'), 'ईमेल, फ़ोन, पता'],
            ['सोशल मीडिया लिंक जोड़ें', setting('facebook') !== '' || setting('youtube') !== '' || setting('twitter') !== '', $s('social'), 'Facebook, YouTube, X…'],
            ['SSL (https) चालू करें', $https, null, 'config/env.php में APP_URL https:// से शुरू हो'],
            ['/install फ़ोल्डर हटाएँ', !is_dir(BASE_PATH . '/install'), null, 'सुरक्षा के लिए सर्वर से हटाएँ'],
            ['DEBUG बंद रखें', !config('app.debug'), null, 'config/env.php में DEBUG => false'],
            ['डिफ़ॉल्ट पेज अपनी संस्था के हिसाब से बदलें', (int) db()->value('SELECT COUNT(*) FROM {p}pages WHERE updated_by IS NOT NULL') > 0, can('pages.view') ? route('admin.pages.index') : null, 'हमारे बारे में, संपर्क, नीतियाँ'],
        ];
    }

    /** बाकी migrations (सिर्फ़ Super Admin को दिखाएँ) */
    public static function pendingMigrations(): array
    {
        if (!is_super_admin()) {
            return [];
        }
        return array_map(static fn($f) => basename($f, '.php'), (new Migrator(db(), BASE_PATH . '/database/migrations'))->pending());
    }
}
