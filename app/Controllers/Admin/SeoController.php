<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Models\NotFound;
use App\Services\AuditService;
use App\Services\NewsQuery;
use App\Services\RedirectService;
use App\Services\SeoAudit;
use App\Services\SettingsSchema;
use App\Services\SitemapService;

/** SEO कमांड सेंटर: डैशबोर्ड, कमियाँ, सेटिंग, 404 मॉनिटर, टूटे लिंक, canonical */
final class SeoController extends Controller
{
    private const TABS = ['seo', 'seo_robots'];

    public function index(Request $request): Response
    {
        $counts = SeoAudit::counts();
        $nf = db()->first("SELECT COUNT(*) c, COALESCE(SUM(hits), 0) h, SUM(is_internal) i FROM {p}not_found_log WHERE status = 'new' AND last_seen >= NOW() - INTERVAL 30 DAY");
        return $this->view('admin/seo/index', [
            'counts' => $counts, 'score' => SeoAudit::score($counts), 'issues' => SeoAudit::issues(), 'site' => SeoAudit::site(),
            'sitemaps' => SitemapService::parts(),
            'news48' => (int) db()->value('SELECT COUNT(*) FROM {p}news n WHERE ' . NewsQuery::PUBLISHED . ' AND n.published_at >= NOW() - INTERVAL 48 HOUR'),
            'nf' => array_map('intval', $nf ?? []),
            'topNf' => db()->all("SELECT * FROM {p}not_found_log WHERE status = 'new' ORDER BY is_internal DESC, hits DESC, last_seen DESC LIMIT 8"),
            'redirects' => db()->first('SELECT COUNT(*) c, COALESCE(SUM(hits), 0) h, SUM(is_auto) a FROM {p}redirects') ?? [],
        ]);
    }

    /** किसी एक कमी वाली ख़बरें */
    public function audit(Request $request): Response
    {
        $issues = SeoAudit::issues();
        $key = $request->str('issue');
        if (!isset($issues[$key])) {
            return $this->toRoute('admin.seo.index');
        }
        $w = NewsQuery::PUBLISHED . ' AND ' . $issues[$key][1];
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value('SELECT COUNT(*) FROM {p}news n WHERE ' . $w);
        $items = db()->all('SELECT n.id, n.title, n.slug, n.meta_title, n.meta_description, n.summary, n.word_count, n.published_at, n.robots, n.canonical_url, c.name cat
            FROM {p}news n LEFT JOIN {p}categories c ON c.id = n.category_id WHERE ' . $w . ' ORDER BY ' . ($key === 'dup_title' ? 'n.title, ' : '') . 'n.published_at DESC LIMIT 30 OFFSET ' . Paginator::offset($page, 30));
        return $this->view('admin/seo/audit', ['key' => $key, 'issue' => $issues[$key], 'issues' => $issues, 'counts' => SeoAudit::counts(), 'items' => new Paginator($items, $total, 30, $page)]);
    }

    public function settings(Request $request, string $tab): Response
    {
        if (!in_array($tab, self::TABS, true)) {
            throw new HttpException(404);
        }
        $schema = SettingsSchema::tab($tab) ?? throw new HttpException(404);
        return $this->view('admin/settings/index', [
            'tabs' => array_intersect_key(SettingsSchema::tabs(), array_flip(self::TABS)), 'active' => $tab, 'schema' => $schema, 'canEdit' => SettingsSchema::canEdit($schema),
            'tabRoute' => 'admin.seo.settings', 'saveRoute' => 'admin.seo.settings.update',
            'section' => ['SEO सेटिंग', route('admin.seo.index'), 'शीर्षक, शेयर इमेज, verification, मुख्य डोमेन, साइटमैप और robots.txt।'],
        ]);
    }

    public function saveSettings(Request $request, string $tab): Response
    {
        if (!in_array($tab, self::TABS, true)) {
            throw new HttpException(404);
        }
        return (new SettingsController())->update($request, $tab); // अनुमति वहीं टैब के हिसाब से (seo.edit / seo.manage)
    }

    /** 404 मॉनिटर */
    public function notFound(Request $request): Response
    {
        $where = ['1=1'];
        $params = [];
        $status = $request->str('status', 'new');
        if (isset(NotFound::STATUSES[$status])) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $view = $request->str('view');
        if ($view === 'internal') {
            $where[] = 'is_internal = 1';
        } elseif ($view === 'people') {
            $where[] = 'hits > 0';
        }
        if (($q = $request->str('q')) !== '') {
            $where[] = 'path LIKE ?';
            $params[] = '%' . addcslashes($q, '%_\\') . '%';
        }
        $w = implode(' AND ', $where);
        $order = $request->str('sort') === 'recent' ? 'last_seen DESC' : 'is_internal DESC, hits DESC, last_seen DESC';
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}not_found_log WHERE $w", $params);
        $items = db()->all("SELECT * FROM {p}not_found_log WHERE $w ORDER BY $order LIMIT 50 OFFSET " . Paginator::offset($page, 50), $params);
        foreach ($items as &$r) {
            $m = RedirectService::match($r['path']);
            $r['redirect'] = $m ? ($m['url'] ?? '410') : null;
        }
        unset($r);
        return $this->view('admin/seo/not-found', ['items' => new Paginator($items, $total, 50, $page), 'status' => $status, 'view' => $view, 'q' => $q,
            'sort' => $request->str('sort'), 'tally' => array_column(db()->all('SELECT status, COUNT(*) c FROM {p}not_found_log GROUP BY status'), 'c', 'status')]);
    }

    /** 404: अनदेखा / वापस / हटाएँ (एक या चुने हुए), या पुराने साफ़ */
    public function notFoundAction(Request $request): Response
    {
        $action = $request->str('action');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('ids', [])))));
        if ($action === 'purge') {
            $n = db()->query("DELETE FROM {p}not_found_log WHERE status <> 'new' OR last_seen < NOW() - INTERVAL 30 DAY")->rowCount();
            AuditService::log('delete', 'seo', null, "404 लॉग साफ़ ($n पंक्तियाँ)");
            return $this->back()->with('success', "$n पुरानी/अनदेखी पंक्तियाँ हटा दी गईं।");
        }
        if (!$ids || !in_array($action, ['ignore', 'restore', 'delete'], true)) {
            return $this->back()->with('warning', 'कोई पंक्ति नहीं चुनी गई।');
        }
        $in = Database::in($ids);
        $n = match ($action) {
            'ignore' => db()->query("UPDATE {p}not_found_log SET status = 'ignored' WHERE id IN ($in)", $ids)->rowCount(),
            'restore' => db()->query("UPDATE {p}not_found_log SET status = 'new' WHERE id IN ($in)", $ids)->rowCount(),
            'delete' => db()->query("DELETE FROM {p}not_found_log WHERE id IN ($in)", $ids)->rowCount(),
        };
        AuditService::log('update', 'seo', null, "404: $action ($n)");
        return $this->back()->with('success', ['ignore' => 'अनदेखा किया', 'restore' => 'वापस सूची में', 'delete' => 'हटाया'][$action] . ": $n");
    }

    /** प्रकाशित ख़बरों में टूटे अंदरूनी लिंक */
    public function links(Request $request): Response
    {
        $run = $request->bool('run');
        $broken = $run ? SeoAudit::brokenLinks() : null;
        $internal = db()->all("SELECT * FROM {p}not_found_log WHERE is_internal = 1 AND status = 'new' ORDER BY hits DESC LIMIT 50");
        return $this->view('admin/seo/links', ['broken' => $broken, 'internal' => $internal]);
    }

    /** canonical किसी और पते पर / noindex वाली सामग्री */
    public function canonical(Request $request): Response
    {
        $news = db()->all("SELECT n.id, n.title, n.slug, n.canonical_url, n.robots, n.status FROM {p}news n WHERE n.deleted_at IS NULL AND (COALESCE(n.canonical_url, '') <> '' OR n.robots LIKE 'noindex%')
            ORDER BY n.updated_at DESC LIMIT 200");
        $pages = db()->all("SELECT id, title, slug, robots, status FROM {p}pages WHERE deleted_at IS NULL AND robots LIKE 'noindex%' ORDER BY title");
        return $this->view('admin/seo/canonical', ['news' => $news, 'pages' => $pages]);
    }
}
