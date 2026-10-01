<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Core\Response;

/**
 * अपना एनालिटिक्स (GA के बिना भी): हर सार्वजनिक पेज-व्यू सर्वर पर दर्ज।
 * - कुकी नहीं: विज़िटर = HMAC(तारीख़ + IP + ब्राउज़र), हर दिन बदलता है (यूनीक विज़िटर रोज़ के हिसाब से)
 * - कच्चे हिट कुछ दिन (सेटिंग), फिर सिर्फ़ रोज़ का सारांश (analytics_daily) रहता है
 * - बॉट, एडमिन पेज, प्रीफ़ेच, 200 के अलावा जवाब, स्टाफ़ (सेटिंग) गिनती में नहीं
 */
final class AnalyticsService
{
    /** रोज़ के सारांश के आयाम */
    public const DIMS = ['all', 'type', 'news', 'item', 'category', 'location', 'reporter', 'device', 'source', 'referrer', 'hour'];

    public const TYPES = [
        'home' => 'होमपेज', 'news' => 'ख़बर', 'category' => 'श्रेणी', 'location' => 'लोकेशन', 'topic' => 'टॉपिक', 'tag' => 'टैग',
        'video' => 'वीडियो', 'gallery' => 'फ़ोटो गैलरी', 'story' => 'वेब स्टोरी', 'audio' => 'ऑडियो/पॉडकास्ट', 'live' => 'लाइव', 'epaper' => 'ई-पेपर',
        'factcheck' => 'फ़ैक्ट चेक', 'election' => 'चुनाव', 'sports' => 'खेल', 'search' => 'खोज', 'page' => 'अन्य पेज',
    ];

    public const DEVICES = ['mobile' => 'मोबाइल', 'desktop' => 'डेस्कटॉप', 'tablet' => 'टैबलेट'];
    public const SOURCES = ['direct' => 'सीधे', 'search' => 'सर्च इंजन', 'social' => 'सोशल मीडिया', 'referral' => 'दूसरी साइटें', 'internal' => 'साइट के अंदर', 'push' => 'पुश/न्यूज़लेटर'];

    private static array $context = [];
    private static bool $skip = false;

    /** controller बताता है कि यह पेज क्या है: type, id, category_id, location_id, reporter_id */
    public static function context(array $c): void
    {
        self::$context = $c + self::$context;
    }

    /** इस अनुरोध को न गिनें (जैसे पूर्वावलोकन) */
    public static function skip(): void
    {
        self::$skip = true;
    }

    public static function enabled(): bool
    {
        return setting('analytics_enabled', '1') === '1';
    }

    /** जवाब भेजने के बाद: गिनने लायक हो तो एक हिट */
    public static function record(Request $request, Response $response): void
    {
        try {
            if (self::$skip || !self::enabled() || $request->method() !== 'GET' || $response->status() !== 200) {
                return;
            }
            $ct = (string) ($response->getHeader('Content-Type') ?? 'text/html');
            if (!str_starts_with($ct, 'text/html')) {
                return;
            }
            $path = '/' . ltrim($request->path(), '/');
            if (preg_match('~^/(admin|api|install|account|reporter|newsletter/unsubscribe|form/[^/]+/done|ad/)~', $path) || NewsQuery::isBot()) {
                return;
            }
            $purpose = strtolower((string) ($_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_PURPOSE'] ?? $_SERVER['HTTP_X_MOZ'] ?? ''));
            if (str_contains($purpose, 'prefetch')) {
                return;
            }
            if (setting('analytics_exclude_staff', '1') === '1' && auth()->check()) {
                return;
            }
            $c = self::$context;
            $type = isset(self::TYPES[$c['type'] ?? '']) ? $c['type'] : ($path === '/' ? 'home' : 'page');
            [$source, $ref] = self::source($request);
            $ua = $request->userAgent();
            db()->insert('analytics_hits', [
                'created_at' => date('Y-m-d H:i:s'), 'day' => date('Y-m-d'), 'visitor' => self::visitor($request->ip(), $ua),
                'path' => mb_substr($path, 0, 255), 'page_type' => $type,
                'content_id' => isset($c['id']) ? (int) $c['id'] : null,
                'category_id' => !empty($c['category_id']) ? (int) $c['category_id'] : null,
                'location_id' => !empty($c['location_id']) ? (int) $c['location_id'] : null,
                'reporter_id' => !empty($c['reporter_id']) ? (int) $c['reporter_id'] : null,
                'device' => self::device($ua), 'source' => $source, 'referrer' => $ref,
            ]);
        } catch (\Throwable $e) {
            logger()->warning('Analytics: ' . $e->getMessage()); // गिनती कभी पेज न रोके
        }
    }

    public static function visitor(string $ip, string $ua): string
    {
        return substr(hash_hmac('sha256', date('Y-m-d') . '|' . $ip . '|' . $ua, (string) config('app.key')), 0, 16);
    }

    public static function device(string $ua): string
    {
        $ua = strtolower($ua);
        if (preg_match('/ipad|tablet|kindle|silk|playbook|(android(?!.*mobile))/', $ua)) {
            return 'tablet';
        }
        return preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile|windows phone/', $ua) ? 'mobile' : 'desktop';
    }

    /** [स्रोत, रेफ़रर होस्ट] — utm_source हो तो वही */
    public static function source(Request $request): array
    {
        $utm = strtolower(trim((string) $request->query('utm_source', '')));
        $medium = strtolower(trim((string) $request->query('utm_medium', '')));
        if ($utm !== '') {
            $utm = mb_substr(preg_replace('/[^a-z0-9._-]/', '', $utm), 0, 60);
            if ($utm === 'pwa') {
                return ['direct', 'pwa']; // Phase 16: होम स्क्रीन ऐप से खोला
            }
            if (in_array($medium, ['push', 'email', 'newsletter', 'notification'], true) || in_array($utm, ['push', 'newsletter', 'email'], true)) {
                return ['push', $utm ?: null];
            }
            return [self::classify($utm) ?? ($medium === 'social' ? 'social' : 'referral'), $utm ?: null];
        }
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = strtolower((string) parse_url($ref, PHP_URL_HOST));
        if ($host === '') {
            return ['direct', null];
        }
        $host = preg_replace('/^(www\.|m\.|l\.|lm\.|mobile\.)/', '', $host);
        $own = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($host === preg_replace('/^www\./', '', $own) || $host === strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''))) {
            return ['internal', null];
        }
        return [self::classify($host) ?? 'referral', mb_substr($host, 0, 120)];
    }

    private static function classify(string $host): ?string
    {
        if (preg_match('/(^|\.)(google|bing|yahoo|duckduckgo|yandex|baidu|ecosia|brave|search)\.|^(google|bing|yahoo)$/', $host)) {
            return 'search';
        }
        if (preg_match('/facebook|fb\.|^fb$|^t\.co$|twitter|^x\.com$|^x$|instagram|linkedin|lnkd|whatsapp|telegram|^t\.me$|youtube|reddit|pinterest|sharechat|koo|threads|snapchat|quora|dailyhunt/', $host)) {
            return 'social';
        }
        return null;
    }

    // ---------- सारांश ----------

    /** एक दिन के कच्चे हिट से analytics_daily दोबारा बनाएँ (बार-बार चलाना सुरक्षित) */
    public static function rollup(?string $day = null): void
    {
        $day ??= date('Y-m-d');
        $w = 'FROM {p}analytics_hits WHERE day = ?';
        $dims = [
            'all' => "''",
            'type' => 'page_type',
            'news' => "IF(page_type = 'news', content_id, NULL)",
            'item' => "IF(page_type NOT IN ('news','home','page','search') AND content_id IS NOT NULL, CONCAT(page_type, ':', content_id), NULL)",
            'category' => 'category_id', 'location' => 'location_id', 'reporter' => 'reporter_id',
            'device' => 'device', 'source' => 'source', 'referrer' => 'referrer', 'hour' => "LPAD(HOUR(created_at), 2, '0')",
        ];
        db()->transaction(static function () use ($day, $w, $dims): void {
            db()->query('DELETE FROM {p}analytics_daily WHERE day = ?', [$day]);
            foreach ($dims as $dim => $expr) {
                db()->query("INSERT INTO {p}analytics_daily (day, dim, dim_key, views, visitors)
                    SELECT day, '$dim', k, COUNT(*), COUNT(DISTINCT visitor) FROM (SELECT day, visitor, $expr AS k $w) x WHERE k IS NOT NULL GROUP BY day, k", [$day]);
            }
        });
    }

    /** शेड्यूलर से: हर ~10 मिनट आज का सारांश; घंटे में एक बार कल का + पुराने हिट की सफ़ाई */
    public static function tick(bool $hourly = false): void
    {
        if (cache()->get('analytics.rollup') === null) {
            cache()->set('analytics.rollup', time(), 600);
            self::rollup();
            cache()->flush('trending');
        }
        if ($hourly) {
            self::rollup(date('Y-m-d', strtotime('-1 day')));
            $keep = max(3, min(365, (int) setting('analytics_raw_days', '35')));
            db()->query('DELETE FROM {p}analytics_hits WHERE day < ? LIMIT 50000', [date('Y-m-d', strtotime("-$keep days"))]);
        }
    }

    /** एडमिन रिपोर्ट खोलते समय आज का सारांश ताज़ा (5 मिनट से पुराना हो तो) */
    public static function fresh(): void
    {
        if (cache()->get('analytics.fresh') === null) {
            cache()->set('analytics.fresh', time(), 300);
            self::rollup();
        }
    }

    // ---------- रिपोर्ट ----------

    /** अभी पढ़ रहे: पिछले N मिनट */
    public static function realtime(int $minutes = 5): array
    {
        $since = date('Y-m-d H:i:s', time() - $minutes * 60);
        $t = db()->first('SELECT COUNT(*) views, COUNT(DISTINCT visitor) visitors FROM {p}analytics_hits WHERE created_at >= ?', [$since]);
        $pages = db()->all('SELECT path, page_type, content_id, COUNT(DISTINCT visitor) readers FROM {p}analytics_hits WHERE created_at >= ? GROUP BY path, page_type, content_id ORDER BY readers DESC LIMIT 10', [$since]);
        $titles = self::titles(array_filter(array_map(static fn($p) => $p['page_type'] === 'news' ? (int) $p['content_id'] : 0, $pages)));
        foreach ($pages as &$p) {
            $p['title'] = $p['page_type'] === 'news' ? ($titles[(int) $p['content_id']] ?? $p['path']) : $p['path'];
            $p['type_label'] = self::TYPES[$p['page_type']] ?? $p['page_type'];
        }
        unset($p);
        $mins = [];
        foreach (db()->all("SELECT DATE_FORMAT(created_at, '%H:%i') m, COUNT(*) c FROM {p}analytics_hits WHERE created_at >= ? GROUP BY m", [date('Y-m-d H:i:00', time() - 29 * 60)]) as $r) {
            $mins[$r['m']] = (int) $r['c'];
        }
        $series = [];
        for ($i = 29; $i >= 0; $i--) {
            $m = date('H:i', time() - $i * 60);
            $series[] = ['m' => $m, 'c' => $mins[$m] ?? 0];
        }
        $devices = db()->all('SELECT device k, COUNT(DISTINCT visitor) c FROM {p}analytics_hits WHERE created_at >= ? GROUP BY device', [$since]);
        $sources = db()->all('SELECT source k, COUNT(DISTINCT visitor) c FROM {p}analytics_hits WHERE created_at >= ? GROUP BY source ORDER BY c DESC', [$since]);
        return ['readers' => (int) $t['visitors'], 'views' => (int) $t['views'], 'pages' => $pages, 'series' => $series,
            'devices' => $devices, 'sources' => $sources, 'minutes' => $minutes, 'at' => date('H:i:s')];
    }

    /** कुल: व्यू, (रोज़ के) यूनीक विज़िटर का जोड़ */
    public static function totals(string $from, string $to): array
    {
        $r = db()->first("SELECT COALESCE(SUM(views),0) views, COALESCE(SUM(visitors),0) visitors, COUNT(*) days FROM {p}analytics_daily WHERE dim = 'all' AND day BETWEEN ? AND ?", [$from, $to]);
        $shares = (int) db()->value('SELECT COALESCE(SUM(shares),0) FROM {p}analytics_shares WHERE day BETWEEN ? AND ?', [$from, $to]);
        $news = (int) db()->value("SELECT COALESCE(SUM(views),0) FROM {p}analytics_daily WHERE dim = 'type' AND dim_key = 'news' AND day BETWEEN ? AND ?", [$from, $to]);
        return ['views' => (int) $r['views'], 'visitors' => (int) $r['visitors'], 'shares' => $shares, 'news_views' => $news,
            'per_visitor' => $r['visitors'] ? round($r['views'] / $r['visitors'], 2) : 0];
    }

    /** रोज़ की शृंखला (ख़ाली दिन 0) */
    public static function series(string $from, string $to, string $dim = 'all', string $key = ''): array
    {
        $rows = [];
        foreach (db()->all('SELECT day, views, visitors FROM {p}analytics_daily WHERE dim = ? AND dim_key = ? AND day BETWEEN ? AND ?', [$dim, $key, $from, $to]) as $r) {
            $rows[$r['day']] = $r;
        }
        $out = [];
        for ($d = strtotime($from); $d <= strtotime($to); $d += 86400) {
            $k = date('Y-m-d', $d);
            $out[] = ['day' => $k, 'views' => (int) ($rows[$k]['views'] ?? 0), 'visitors' => (int) ($rows[$k]['visitors'] ?? 0)];
        }
        return $out;
    }

    /** किसी आयाम में सबसे ऊपर: [key, views, visitors] */
    public static function top(string $dim, string $from, string $to, int $limit = 10, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        return db()->all("SELECT dim_key k, SUM(views) views, SUM(visitors) visitors FROM {p}analytics_daily WHERE dim = ? AND day BETWEEN ? AND ?
            GROUP BY dim_key ORDER BY views DESC LIMIT $limit OFFSET " . max(0, $offset), [$dim, $from, $to]);
    }

    public static function countKeys(string $dim, string $from, string $to): int
    {
        return (int) db()->value('SELECT COUNT(DISTINCT dim_key) FROM {p}analytics_daily WHERE dim = ? AND day BETWEEN ? AND ?', [$dim, $from, $to]);
    }

    /** घंटे के हिसाब से (00–23) */
    public static function hours(string $from, string $to): array
    {
        $h = array_fill(0, 24, 0);
        foreach (self::top('hour', $from, $to, 24) as $r) {
            $h[(int) $r['k']] = (int) $r['views'];
        }
        return $h;
    }

    /** ख़बरें: views + visitors + शेयर + नाम */
    public static function topNews(string $from, string $to, int $limit = 10, int $offset = 0): array
    {
        $rows = self::top('news', $from, $to, $limit, $offset);
        if (!$rows) {
            return [];
        }
        $ids = array_map(static fn($r) => (int) $r['k'], $rows);
        $in = \App\Core\Database::in($ids);
        $meta = [];
        foreach (db()->all("SELECT n.id, n.title, n.slug, n.published_at, n.views total_views, c.name category, u.name reporter
            FROM {p}news n LEFT JOIN {p}categories c ON c.id = n.category_id LEFT JOIN {p}users u ON u.id = n.reporter_id WHERE n.id IN ($in)", $ids) as $m) {
            $meta[(int) $m['id']] = $m;
        }
        $shares = [];
        foreach (db()->all("SELECT news_id, SUM(shares) s FROM {p}analytics_shares WHERE news_id IN ($in) AND day BETWEEN ? AND ? GROUP BY news_id", [...$ids, $from, $to]) as $s) {
            $shares[(int) $s['news_id']] = (int) $s['s'];
        }
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['k'];
            if (isset($meta[$id])) {
                $out[] = $meta[$id] + ['views' => (int) $r['views'], 'visitors' => (int) $r['visitors'], 'shares' => $shares[$id] ?? 0];
            }
        }
        return $out;
    }

    /** category/location/reporter की key को नाम */
    public static function named(string $dim, array $rows): array
    {
        $ids = array_filter(array_map(static fn($r) => (int) $r['k'], $rows));
        $names = [];
        if ($ids) {
            $table = ['category' => 'categories', 'location' => 'locations', 'reporter' => 'users'][$dim] ?? null;
            if ($table) {
                foreach (db()->all("SELECT id, name FROM {p}$table WHERE id IN (" . \App\Core\Database::in($ids) . ')', array_values($ids)) as $n) {
                    $names[(int) $n['id']] = $n['name'];
                }
            }
        }
        $labels = ['type' => self::TYPES, 'device' => self::DEVICES, 'source' => self::SOURCES][$dim] ?? [];
        foreach ($rows as &$r) {
            $r['name'] = $labels[$r['k']] ?? $names[(int) $r['k']] ?? $r['k'];
        }
        return $rows;
    }

    /** विज्ञापन: इंप्रेशन, क्लिक, CTR (Phase 9 की टेबल से) */
    public static function ads(string $from, string $to, int $limit = 8): array
    {
        $t = db()->first('SELECT COALESCE(SUM(impressions),0) imp, COALESCE(SUM(clicks),0) clk FROM {p}ad_stats_daily WHERE day BETWEEN ? AND ?', [$from, $to]);
        $top = db()->all("SELECT a.id, a.name AS title, SUM(s.impressions) imp, SUM(s.clicks) clk FROM {p}ad_stats_daily s JOIN {p}ads a ON a.id = s.ad_id
            WHERE s.day BETWEEN ? AND ? GROUP BY a.id ORDER BY imp DESC LIMIT " . max(1, $limit), [$from, $to]);
        return ['imp' => (int) $t['imp'], 'clk' => (int) $t['clk'], 'ctr' => $t['imp'] ? round($t['clk'] * 100 / $t['imp'], 2) : 0, 'top' => $top];
    }

    /** ई-पेपर पढ़ाई: इस अवधि के हिट (इश्यू के हिसाब से) + कुल व्यू */
    public static function epaper(string $from, string $to, int $limit = 8): array
    {
        $reads = (int) db()->value("SELECT COALESCE(SUM(views),0) FROM {p}analytics_daily WHERE dim = 'type' AND dim_key = 'epaper' AND day BETWEEN ? AND ?", [$from, $to]);
        $rows = self::top('item', $from, $to, 200);
        $ids = [];
        foreach ($rows as $r) {
            if (str_starts_with($r['k'], 'epaper:')) {
                $ids[(int) substr($r['k'], 7)] = (int) $r['views'];
            }
        }
        $top = [];
        if ($ids) {
            foreach (db()->all('SELECT i.id, i.title, i.issue_date, i.views total, e.name edition FROM {p}epaper_issues i JOIN {p}epaper_editions e ON e.id = i.edition_id WHERE i.id IN (' . \App\Core\Database::in(array_keys($ids)) . ')', array_keys($ids)) as $i) {
                $top[] = $i + ['reads' => $ids[(int) $i['id']]];
            }
            usort($top, static fn($a, $b) => $b['reads'] <=> $a['reads']);
        }
        return ['reads' => $reads, 'top' => array_slice($top, 0, $limit)];
    }

    /** शेयर: नेटवर्क के हिसाब से + सबसे ज़्यादा शेयर हुई ख़बरें */
    public static function shares(string $from, string $to, int $limit = 8): array
    {
        $net = db()->all('SELECT network k, SUM(shares) c FROM {p}analytics_shares WHERE day BETWEEN ? AND ? GROUP BY network ORDER BY c DESC', [$from, $to]);
        $top = db()->all('SELECT n.id, n.title, SUM(s.shares) c FROM {p}analytics_shares s JOIN {p}news n ON n.id = s.news_id WHERE s.day BETWEEN ? AND ? GROUP BY n.id ORDER BY c DESC LIMIT ' . max(1, $limit), [$from, $to]);
        return ['networks' => $net, 'top' => $top];
    }

    public static function titles(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $out = [];
        foreach (db()->all('SELECT id, title FROM {p}news WHERE id IN (' . \App\Core\Database::in($ids) . ')', $ids) as $r) {
            $out[(int) $r['id']] = $r['title'];
        }
        return $out;
    }

    // ---------- शेयर बीकन ----------
    public const NETWORKS = ['whatsapp' => 'WhatsApp', 'facebook' => 'Facebook', 'x' => 'X (Twitter)', 'telegram' => 'Telegram', 'linkedin' => 'LinkedIn', 'copy' => 'लिंक कॉपी', 'native' => 'फ़ोन से शेयर', 'email' => 'ईमेल'];

    /** एक सत्र में एक ख़बर का एक नेटवर्क एक बार */
    public static function share(int $newsId, string $network): bool
    {
        if (!isset(self::NETWORKS[$network]) || NewsQuery::isBot()) {
            return false;
        }
        if (!db()->value("SELECT id FROM {p}news WHERE id = ? AND status = 'published'", [$newsId])) {
            return false;
        }
        $seen = (array) app('session')->get('shared', []);
        $key = $newsId . ':' . $network;
        if (in_array($key, $seen, true)) {
            return true;
        }
        $seen[] = $key;
        app('session')->set('shared', array_slice($seen, -100));
        db()->query('INSERT INTO {p}analytics_shares (day, news_id, network, shares) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE shares = shares + 1', [date('Y-m-d'), $newsId, $network]);
        db()->query('UPDATE {p}news SET shares = shares + 1 WHERE id = ?', [$newsId]);
        return true;
    }

    /** अवधि: range=today|7|30|90|custom (from/to) → [from, to, पिछली अवधि from, to, label] */
    public static function range(Request $request): array
    {
        $r = (string) $request->query('range', '7');
        $today = date('Y-m-d');
        if ($r === 'custom') {
            $from = (string) $request->query('from', '');
            $to = (string) $request->query('to', '');
            $ok = static fn($d) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d);
            if (!$ok($from) || !$ok($to) || $from > $to) {
                $r = '7';
            } else {
                $to = min($to, $today);
                $from = max($from, date('Y-m-d', strtotime($to . ' -365 days')));
            }
        }
        if ($r !== 'custom') {
            $r = in_array($r, ['today', 'yesterday', '7', '30', '90'], true) ? $r : '7';
            [$from, $to] = match ($r) {
                'today' => [$today, $today],
                'yesterday' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
                default => [date('Y-m-d', strtotime('-' . ((int) $r - 1) . ' days')), $today],
            };
        }
        $len = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
        $pto = date('Y-m-d', strtotime($from . ' -1 day'));
        $pfrom = date('Y-m-d', strtotime($pto . ' -' . ($len - 1) . ' days'));
        $label = ['today' => 'आज', 'yesterday' => 'कल', '7' => 'पिछले 7 दिन', '30' => 'पिछले 30 दिन', '90' => 'पिछले 90 दिन'][$r] ?? (hindi_date($from) . ' – ' . hindi_date($to));
        return ['key' => $r, 'from' => $from, 'to' => $to, 'pfrom' => $pfrom, 'pto' => $pto, 'days' => $len, 'label' => $label];
    }

    /** % बदलाव */
    public static function change(int|float $now, int|float $before): ?float
    {
        return $before > 0 ? round(($now - $before) * 100 / $before, 1) : null;
    }
}
