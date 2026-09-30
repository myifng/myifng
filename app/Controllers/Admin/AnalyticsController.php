<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Services\AnalyticsService as A;
use App\Services\AuditService;
use App\Services\NewsService;
use App\Services\TrendingService;

/** एनालिटिक्स (§62): ओवरव्यू, रियलटाइम, कंटेंट/श्रेणी/रिपोर्टर/लोकेशन प्रदर्शन, एक ख़बर का हाल, ट्रेंडिंग नियंत्रण */
final class AnalyticsController extends Controller
{
    /** रिपोर्ट के आयाम: [नाम, आइकन] */
    public const DIMS = [
        'news' => ['ख़बरें', 'fa-newspaper'], 'category' => ['श्रेणियाँ', 'fa-folder-tree'], 'location' => ['लोकेशन', 'fa-map-location-dot'],
        'reporter' => ['रिपोर्टर', 'fa-id-card'], 'item' => ['अन्य सामग्री', 'fa-photo-film'], 'type' => ['पेज के प्रकार', 'fa-shapes'],
        'source' => ['ट्रैफ़िक स्रोत', 'fa-signs-post'], 'referrer' => ['रेफ़रर साइटें', 'fa-arrow-up-right-from-square'], 'device' => ['डिवाइस', 'fa-mobile-screen'],
    ];

    public const HOURS = ['6' => '6 घंटे', '24' => '24 घंटे', '72' => '3 दिन', '168' => '7 दिन', '0' => 'जब तक हटाएँ नहीं'];

    public function index(Request $request): Response
    {
        A::fresh();
        $r = A::range($request);
        [$f, $t] = [$r['from'], $r['to']];
        $now = A::totals($f, $t);
        $before = A::totals($r['pfrom'], $r['pto']);
        $series = A::series($f, $t);
        $prev = A::series($r['pfrom'], $r['pto']);
        $top = [];
        foreach (['category', 'location', 'reporter', 'device', 'source', 'referrer', 'type'] as $dim) {
            $top[$dim] = A::named($dim, A::top($dim, $f, $t, 8));
        }
        return $this->view('admin/analytics/index', [
            'r' => $r, 'now' => $now, 'before' => $before, 'series' => $series, 'prev' => $prev, 'hours' => A::hours($f, $t),
            'news' => A::topNews($f, $t, 10), 'top' => $top, 'shares' => A::shares($f, $t), 'epaper' => A::epaper($f, $t), 'ads' => A::ads($f, $t),
            'realtime' => A::realtime(), 'enabled' => A::enabled(),
            'ga' => ['ga' => (string) setting('ga_id'), 'gtm' => (string) setting('gtm_id')],
        ]);
    }

    /** रियलटाइम (हर 15 सेकंड JS से) */
    public function realtime(Request $request): Response
    {
        return $this->json(['ok' => true] + A::realtime())->header('Cache-Control', 'no-store');
    }

    /** किसी आयाम की पूरी सूची (पेज के साथ) */
    public function content(Request $request, string $dim = 'news'): Response
    {
        if (!isset(self::DIMS[$dim])) {
            throw new HttpException(404);
        }
        A::fresh();
        $r = A::range($request);
        $page = max(1, $request->int('page', 1));
        $per = 50;
        $total = A::countKeys($dim, $r['from'], $r['to']);
        $rows = $this->rows($dim, $r['from'], $r['to'], $per, Paginator::offset($page, $per));
        return $this->view('admin/analytics/content', ['dim' => $dim, 'r' => $r, 'items' => new Paginator($rows, $total, $per, $page),
            'sum' => A::totals($r['from'], $r['to'])]);
    }

    public function export(Request $request, string $dim = 'news'): Response
    {
        if (!isset(self::DIMS[$dim])) {
            throw new HttpException(404);
        }
        $r = A::range($request);
        $rows = $this->rows($dim, $r['from'], $r['to'], 500, 0);
        AuditService::log('export', 'analytics', null, 'एनालिटिक्स CSV: ' . self::DIMS[$dim][0] . ' (' . $r['from'] . ' – ' . $r['to'] . ')');
        $head = $dim === 'news' ? ['ID', 'शीर्षक', 'श्रेणी', 'रिपोर्टर', 'प्रकाशित', 'व्यू', 'विज़िटर', 'शेयर', 'URL'] : ['नाम', 'कुंजी', 'व्यू', 'विज़िटर'];
        $out = [];
        foreach ($rows as $x) {
            $out[] = $dim === 'news'
                ? [$x['id'], $x['title'], $x['category'], $x['reporter'], $x['published_at'], $x['views'], $x['visitors'], $x['shares'], NewsService::url($x)]
                : [$x['name'], $x['k'], $x['views'], $x['visitors']];
        }
        return Response::csv('analytics-' . $dim . '-' . $r['from'] . '-' . $r['to'] . '.csv', $head, $out);
    }

    /** एक ख़बर का प्रदर्शन */
    public function news(Request $request, int $id): Response
    {
        $n = db()->first('SELECT n.*, c.name category, u.name reporter FROM {p}news n LEFT JOIN {p}categories c ON c.id = n.category_id LEFT JOIN {p}users u ON u.id = n.reporter_id WHERE n.id = ?', [$id])
            ?? throw new HttpException(404);
        if (!can('news.view')) {
            throw new HttpException(403);
        }
        A::fresh();
        $from = date('Y-m-d', max(strtotime('-29 days'), strtotime((string) ($n['published_at'] ?: $n['created_at']))));
        $to = date('Y-m-d');
        $series = A::series($from, $to, 'news', (string) $id);
        $raw = static fn(string $col) => db()->all("SELECT $col k, COUNT(*) c FROM {p}analytics_hits WHERE page_type = 'news' AND content_id = ? GROUP BY $col ORDER BY c DESC LIMIT 10", [$id]);
        $firstDay = db()->first("SELECT COALESCE(SUM(views),0) v, COALESCE(SUM(visitors),0) u FROM {p}analytics_daily WHERE dim = 'news' AND dim_key = ? AND day BETWEEN ? AND ?",
            [(string) $id, date('Y-m-d', strtotime((string) $n['published_at'] ?: 'now')), date('Y-m-d', strtotime(($n['published_at'] ?: 'now') . ' +1 day'))]);
        $rank = null;
        $v7 = (int) db()->value("SELECT COALESCE(SUM(views),0) FROM {p}analytics_daily WHERE dim = 'news' AND dim_key = ? AND day >= ?", [(string) $id, date('Y-m-d', strtotime('-6 days'))]);
        if ($v7) {
            $rank = 1 + (int) db()->value("SELECT COUNT(*) FROM (SELECT dim_key, SUM(views) v FROM {p}analytics_daily WHERE dim = 'news' AND day >= ? GROUP BY dim_key HAVING v > ?) x", [date('Y-m-d', strtotime('-6 days')), $v7]);
        }
        $ov = db()->first('SELECT * FROM {p}trending_overrides WHERE news_id = ? AND (until IS NULL OR until > NOW())', [$id]);
        return $this->view('admin/analytics/news', [
            'n' => $n, 'series' => $series, 'from' => $from,
            'sum' => ['views' => array_sum(array_column($series, 'views')), 'visitors' => array_sum(array_column($series, 'visitors')), 'total' => (int) $n['views'], 'shares' => (int) ($n['shares'] ?? 0), 'first' => $firstDay, 'v7' => $v7, 'rank' => $rank],
            'sources' => $this->label('source', $raw('source')), 'devices' => $this->label('device', $raw('device')), 'referrers' => $raw('referrer'),
            'networks' => db()->all('SELECT network k, SUM(shares) c FROM {p}analytics_shares WHERE news_id = ? GROUP BY network ORDER BY c DESC', [$id]),
            'hours' => db()->all("SELECT HOUR(created_at) h, COUNT(*) c FROM {p}analytics_hits WHERE page_type = 'news' AND content_id = ? GROUP BY h", [$id]),
            'override' => $ov, 'canOverride' => can('news.publish'),
        ]);
    }

    // ---------- ट्रेंडिंग नियंत्रण ----------
    public function trending(Request $request): Response
    {
        A::fresh();
        $scores = TrendingService::scores();
        $overrides = db()->all('SELECT o.*, n.title, n.slug, n.status, u.name by_name FROM {p}trending_overrides o JOIN {p}news n ON n.id = o.news_id LEFT JOIN {p}users u ON u.id = o.created_by
            WHERE o.until IS NULL OR o.until > NOW() ORDER BY o.action, o.created_at DESC');
        $flagged = db()->all("SELECT id, title, published_at FROM {p}news WHERE is_trending = 1 AND status = 'published' AND deleted_at IS NULL ORDER BY published_at DESC LIMIT 20");
        return $this->view('admin/analytics/trending', [
            'items' => TrendingService::trending(20), 'scores' => $scores, 'overrides' => $overrides, 'flagged' => $flagged,
            'read' => TrendingService::mostRead(1, 10), 'shared' => TrendingService::mostShared(7, 10), 'tags' => TrendingService::tags(12),
            'canOverride' => can('news.publish'),
        ]);
    }

    /** पिन/छुपाएँ: ख़बर का ID, URL या स्लग */
    public function override(Request $request): Response
    {
        $this->mayOverride();
        $action = (string) $request->input('action');
        $hours = (string) $request->input('hours', '24');
        if (!in_array($action, ['pin', 'hide'], true) || !isset(self::HOURS[$hours])) {
            return $this->back()->with('danger', 'विकल्प सही नहीं।');
        }
        $ref = trim((string) $request->input('news'));
        $slug = preg_match('~/news/([a-z0-9-]+)~', $ref, $m) ? $m[1] : $ref;
        $n = ctype_digit($ref) ? db()->first('SELECT id, title, status FROM {p}news WHERE id = ?', [(int) $ref])
            : db()->first('SELECT id, title, status FROM {p}news WHERE slug = ?', [$slug]);
        if (!$n || $n['status'] !== 'published') {
            return $this->back()->withInput($request->post())->with('danger', 'प्रकाशित ख़बर नहीं मिली। ख़बर का ID, पूरा पता या स्लग लिखें।');
        }
        db()->query('REPLACE INTO {p}trending_overrides (news_id, action, until, created_by, created_at) VALUES (?, ?, ?, ?, NOW())',
            [$n['id'], $action, $hours === '0' ? null : date('Y-m-d H:i:s', time() + (int) $hours * 3600), auth()->id()]);
        TrendingService::flush();
        AuditService::log('update', 'analytics', (int) $n['id'], ($action === 'pin' ? 'ट्रेंडिंग में पिन: ' : 'ट्रेंडिंग/लोकप्रिय से छुपाई: ') . $n['title'] . ' (' . self::HOURS[$hours] . ')');
        return $this->back()->with('success', $action === 'pin' ? 'ख़बर ट्रेंडिंग में सबसे ऊपर पिन हो गई।' : 'ख़बर ट्रेंडिंग और लोकप्रिय सूचियों से छुप गई।');
    }

    public function removeOverride(Request $request, int $id): Response
    {
        $this->mayOverride();
        db()->query('DELETE FROM {p}trending_overrides WHERE news_id = ?', [$id]);
        TrendingService::flush();
        AuditService::log('update', 'analytics', $id, 'ट्रेंडिंग ओवरराइड हटाया');
        return $this->back()->with('success', 'ओवरराइड हट गया; ख़बर अब अपने स्कोर से दिखेगी।');
    }

    private function mayOverride(): void
    {
        if (!can('news.publish')) {
            throw new HttpException(403, 'ट्रेंडिंग बदलने के लिए ख़बर प्रकाशित करने की अनुमति चाहिए।');
        }
    }

    // ---------- सहायक ----------
    private function rows(string $dim, string $from, string $to, int $limit, int $offset): array
    {
        if ($dim === 'news') {
            return A::topNews($from, $to, $limit, $offset);
        }
        $rows = A::top($dim, $from, $to, $limit, $offset);
        return $dim === 'item' ? $this->itemNames($rows) : A::named($dim, $rows);
    }

    /** "video:12" जैसी key को नाम */
    private function itemNames(array $rows): array
    {
        $tables = ['video' => ['videos', 'title'], 'gallery' => ['galleries', 'title'], 'story' => ['web_stories', 'title'], 'audio' => ['audio_items', 'title'],
            'epaper' => ['epaper_issues', 'title'], 'category' => ['categories', 'name'], 'location' => ['locations', 'name'], 'topic' => ['topics', 'name'], 'tag' => ['tags', 'name']];
        $want = [];
        foreach ($rows as $r) {
            [$type, $id] = array_pad(explode(':', (string) $r['k'], 2), 2, '0');
            $want[$type][] = (int) $id;
        }
        $names = [];
        foreach ($want as $type => $ids) {
            if (isset($tables[$type])) {
                [$tb, $col] = $tables[$type];
                foreach (db()->all("SELECT id, $col n FROM {p}$tb WHERE id IN (" . Database::in($ids) . ')', $ids) as $x) {
                    $names[$type . ':' . $x['id']] = $x['n'];
                }
            }
        }
        foreach ($rows as &$r) {
            $type = strstr((string) $r['k'], ':', true);
            $r['name'] = ($names[$r['k']] ?? '#' . substr((string) strstr((string) $r['k'], ':'), 1)) . ' · ' . (A::TYPES[$type] ?? $type);
        }
        unset($r);
        return $rows;
    }

    private function label(string $dim, array $rows): array
    {
        $l = $dim === 'source' ? A::SOURCES : A::DEVICES;
        foreach ($rows as &$r) {
            $r['name'] = $l[$r['k']] ?? $r['k'];
        }
        unset($r);
        return $rows;
    }
}
