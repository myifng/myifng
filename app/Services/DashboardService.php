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
            if (!empty($data['skip'])) {
                continue;
            }
            $out[$key] = $c + $data + ['link' => ($c['route'] && app('router')->has($c['route'])) ? route($c['route']) . (!empty($c['query']) ? '?' . $c['query'] : '') : null];
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

    private static function newsToday(): array
    {
        [$scope, $p] = NewsService::scope('n');
        $r = db()->first("SELECT SUM(n.status = 'published' AND DATE(n.published_at) = CURDATE()) today, SUM(n.status = 'published') total
                          FROM {p}news n WHERE $scope AND n.deleted_at IS NULL", $p);
        return ['value' => (int) $r['today'], 'sub' => 'कुल प्रकाशित: ' . num($r['total'])];
    }

    private static function newsPending(): array
    {
        $r = db()->first("SELECT SUM(status = 'submitted') s, SUM(status = 'review') r, SUM(status = 'fact_check') f FROM {p}news WHERE deleted_at IS NULL");
        $n = (int) $r['s'] + (int) $r['r'] + (int) $r['f'];
        return ['value' => $n, 'sub' => 'नई ' . num($r['s']) . ' · समीक्षा ' . num($r['r']) . ' · फ़ैक्ट चेक ' . num($r['f']), 'alert' => (int) $r['s'] > 0];
    }

    private static function newsMine(): array
    {
        $uid = auth()->id();
        $r = db()->first("SELECT SUM(status = 'draft') d, SUM(status = 'rejected') rj, SUM(status = 'published') p FROM {p}news WHERE (reporter_id = ? OR created_by = ?) AND deleted_at IS NULL", [$uid, $uid]);
        return ['value' => (int) $r['p'], 'sub' => 'प्रकाशित · ड्राफ़्ट ' . num($r['d']) . ' · सुधार माँगे ' . num($r['rj']), 'alert' => (int) $r['rj'] > 0];
    }

    private static function newsScheduled(): array
    {
        $r = db()->first("SELECT COUNT(*) c, MIN(scheduled_at) nxt FROM {p}news WHERE status = 'scheduled' AND deleted_at IS NULL");
        return ['value' => (int) $r['c'], 'sub' => $r['nxt'] ? 'अगली: ' . hindi_date($r['nxt'], true) : 'कोई शेड्यूल नहीं'];
    }

    private static function myAssignments(): array
    {
        $r = db()->first("SELECT SUM(status IN ('open','accepted','in_progress')) open, SUM(status IN ('open','accepted','in_progress') AND deadline < NOW()) late
                          FROM {p}assignments WHERE reporter_id = ?", [auth()->id()]);
        return ['value' => (int) $r['open'], 'sub' => (int) $r['late'] ? 'देर हो चुकी: ' . num($r['late']) : 'खुले असाइनमेंट', 'alert' => (int) $r['late'] > 0];
    }

    private static function reporters(): array
    {
        $r = db()->first("SELECT SUM(status = 'active') active, SUM(status = 'active' AND valid_until <= CURDATE() + INTERVAL 30 DAY) expiring FROM {p}reporters");
        return ['value' => (int) $r['active'], 'sub' => (int) $r['expiring'] ? '30 दिन में वैधता ख़त्म: ' . num($r['expiring']) : 'सभी की वैधता ठीक', 'alert' => (int) $r['expiring'] > 0];
    }

    private static function applications(): array
    {
        $r = db()->first("SELECT SUM(status = 'new') n, SUM(status NOT IN ('approved','rejected')) open FROM {p}reporter_applications");
        return ['value' => (int) $r['n'], 'sub' => 'कुल खुले: ' . num($r['open']), 'alert' => (int) $r['n'] > 0];
    }

    /** सिर्फ़ उन्हें जिनका रिपोर्टर प्रोफ़ाइल है */
    private static function myCard(): array
    {
        $r = ReporterService::forUser((int) auth()->id());
        if (!$r) {
            return ['skip' => true];
        }
        $days = (int) floor((strtotime((string) $r['valid_until']) - strtotime('today')) / 86400);
        return ['value' => $r['reporter_code'], 'sub' => $days >= 0 ? 'वैधता: ' . hindi_date($r['valid_until']) : 'वैधता ख़त्म, नवीनीकरण कराएँ', 'alert' => $days < 30];
    }

    private static function breaking(): array
    {
        $n = BreakingService::countActive();
        $urgent = (int) db()->value('SELECT COUNT(*) FROM {p}breaking_news b WHERE ' . BreakingService::ACTIVE . ' AND b.priority = 3');
        return ['value' => $n, 'sub' => $n ? ($urgent ? 'अति ज़रूरी: ' . num($urgent) : 'टिकर पर चल रहे') : 'अभी कोई ब्रेकिंग नहीं', 'alert' => $urgent > 0];
    }

    private static function liveBlogs(): array
    {
        $r = db()->first("SELECT SUM(status = 'live') live, SUM(status = 'paused') paused FROM {p}live_blogs");
        return ['value' => (int) $r['live'], 'sub' => (int) $r['paused'] ? 'रुके हुए: ' . num($r['paused']) : 'लाइव ब्लॉग', 'alert' => false];
    }

    private static function multimedia(): array
    {
        $r = db()->first("SELECT (SELECT COUNT(*) FROM {p}videos WHERE status = 'published') v, (SELECT COUNT(*) FROM {p}galleries WHERE status = 'published') g,
                          (SELECT COUNT(*) FROM {p}web_stories WHERE status = 'published') s, (SELECT COUNT(*) FROM {p}audio_items WHERE status = 'published') a");
        return ['value' => (int) $r['v'], 'sub' => 'गैलरी ' . num($r['g']) . ' · स्टोरी ' . num($r['s']) . ' · ऑडियो ' . num($r['a']), 'alert' => false];
    }

    private static function epaperToday(): array
    {
        $r = db()->first("SELECT COUNT(*) editions, SUM(i.status = 'published' AND i.publish_at <= NOW()) live, SUM(i.status = 'published' AND i.publish_at > NOW()) sched
                          FROM {p}epaper_editions e LEFT JOIN {p}epaper_issues i ON i.edition_id = e.id AND i.issue_date = CURDATE() WHERE e.status = 'active'");
        $left = (int) $r['editions'] - (int) $r['live'] - (int) $r['sched'];
        return ['value' => (int) $r['live'], 'sub' => num($r['editions']) . ' संस्करण में से प्रकाशित' . ($left > 0 ? ' · बाकी: ' . num($left) : ((int) $r['sched'] ? ' · शेड्यूल: ' . num($r['sched']) : '')), 'alert' => $left > 0];
    }

    private static function adsRunning(): array
    {
        $n = (int) db()->value("SELECT COUNT(*) FROM {p}ads WHERE status = 'active' AND (start_at IS NULL OR start_at <= NOW()) AND (end_at IS NULL OR end_at > NOW())");
        $t = db()->first('SELECT COALESCE(SUM(impressions), 0) i, COALESCE(SUM(clicks), 0) c FROM {p}ad_stats_daily WHERE day = CURDATE()');
        return ['value' => $n, 'sub' => 'आज: ' . num($t['i']) . ' इम्प्रेशन · ' . num($t['c']) . ' क्लिक', 'alert' => false];
    }

    private static function adRevenue(): array
    {
        $r = db()->first("SELECT (SELECT COALESCE(SUM(amount), 0) FROM {p}ad_payments WHERE DATE_FORMAT(paid_on, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')) m,
            (SELECT COALESCE(SUM(total - paid), 0) FROM {p}ad_invoices WHERE status IN ('sent','partial') AND due_date < CURDATE()) o");
        return ['value' => (int) round((float) $r['m']), 'sub' => (float) $r['o'] > 0 ? 'देर वाला बकाया: ' . AdvertiserService::money($r['o']) : 'इस महीने की आमदनी (₹)', 'alert' => (float) $r['o'] > 0];
    }

    private static function media(): array
    {
        $r = db()->first("SELECT COUNT(*) total, SUM(kind = 'image') images, COALESCE(SUM(size), 0) bytes FROM {p}media WHERE deleted_at IS NULL");
        return ['value' => (int) $r['total'], 'sub' => 'इमेज: ' . num($r['images']) . ' · ' . MediaService::humanSize((int) $r['bytes'])];
    }

    private static function categories(): array
    {
        $r = db()->first("SELECT SUM(parent_id IS NULL) main, SUM(parent_id IS NOT NULL) sub FROM {p}categories WHERE status = 'active'");
        return ['value' => (int) $r['main'], 'sub' => 'मुख्य · उप-श्रेणियाँ: ' . num($r['sub'])];
    }

    private static function locations(): array
    {
        $r = db()->first("SELECT SUM(type = 'state') states, SUM(type = 'district') districts, COUNT(*) total FROM {p}locations");
        return ['value' => (int) $r['districts'], 'sub' => 'ज़िले · राज्य: ' . num($r['states']) . ' · कुल ' . num($r['total'])];
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
