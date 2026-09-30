<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Services\FactCheckService as FC;
use App\Services\NewsQuery;
use App\Services\SeoService;

/** सार्वजनिक फ़ैक्ट चेक */
final class FactCheckController extends FrontController
{
    public function index(Request $request): Response
    {
        $verdict = $request->str('verdict');
        $verdict = isset(FC::VERDICTS[$verdict]) ? $verdict : null;
        $page = $this->page($request->int('page', 1));
        $total = (int) db()->value('SELECT COUNT(*) FROM {p}fact_checks f WHERE ' . FC::PUBLISHED . ($verdict ? ' AND f.verdict = ?' : ''), $verdict ? [$verdict] : []);
        $items = FC::latest(20, $verdict, Paginator::offset($page, 20));
        $counts = [];
        foreach (db()->all('SELECT verdict, COUNT(*) c FROM {p}fact_checks f WHERE ' . FC::PUBLISHED . ' GROUP BY verdict') as $r) {
            $counts[$r['verdict']] = (int) $r['c'];
        }
        \App\Services\AnalyticsService::context(['type' => 'page']);
        $title = 'फ़ैक्ट चेक' . ($verdict ? ': ' . FC::VERDICTS[$verdict][0] : '');
        return $this->view('front/fact-check/index', ['items' => new Paginator($items, $total, 20, $page), 'verdict' => $verdict, 'counts' => $counts, 'side' => $this->sidebar(),
            'seo' => ['title' => $title . ($page > 1 ? ' - पेज ' . $page : ''), 'description' => 'वायरल दावों, फ़र्ज़ी ख़बरों और भ्रामक वीडियो की जाँच: सच क्या है, ' . setting('site_name') . ' फ़ैक्ट चेक।',
                'canonical' => route('factcheck.index') . ($verdict ? '?verdict=' . $verdict : '') . ($page > 1 ? ($verdict ? '&' : '?') . 'page=' . $page : ''), 'robots' => $verdict ? 'noindex,follow' : 'index,follow']]);
    }

    public function show(Request $request, string $slug): Response
    {
        $f = db()->first('SELECT f.*, c.name category, c.slug category_slug, l.name location, u.name author, r.name reviewer FROM {p}fact_checks f
            LEFT JOIN {p}categories c ON c.id = f.category_id LEFT JOIN {p}locations l ON l.id = f.location_id LEFT JOIN {p}users u ON u.id = f.author_id LEFT JOIN {p}users r ON r.id = f.reviewer_id
            WHERE f.slug = ? AND ' . FC::PUBLISHED, [$slug]);
        if (!$f) {
            throw new HttpException(404);
        }
        NewsQuery::hit('fact_checks', (int) $f['id']);
        \App\Services\AnalyticsService::context(['type' => 'factcheck', 'id' => $f['id'], 'category_id' => $f['category_id'], 'location_id' => $f['location_id']]);
        $news = $f['news_id'] ? NewsQuery::list('n.id = ?', [(int) $f['news_id']], 1)[0] ?? null : null;
        $more = db()->all('SELECT f.title, f.slug, f.verdict, f.image, f.published_at FROM {p}fact_checks f WHERE ' . FC::PUBLISHED . ' AND f.id <> ? ORDER BY f.published_at DESC LIMIT 4', [$f['id']]);
        $url = FC::url($f);
        $crumbs = [['होम', url()], ['फ़ैक्ट चेक', route('factcheck.index')], [$f['title'], null]];
        return $this->view('front/fact-check/show', ['f' => $f, 'sources' => FC::sources($f['sources']), 'news' => $news, 'more' => $more, 'shareUrl' => $url, 'crumbs' => $crumbs, 'side' => $this->sidebar(),
            'seo' => ['title' => $f['meta_title'] ?: $f['title'], 'description' => $f['meta_description'] ?: (($f['summary'] ?: mb_substr(strip_tags((string) $f['claim']), 0, 150)) . ' — फ़ैसला: ' . FC::verdict($f['verdict'])[0]),
                'canonical' => $url, 'image' => $f['image'], 'type' => 'article', 'published' => $f['published_at'], 'modified' => $f['updated_verdict_at'] ?: $f['updated_at'],
                'jsonld' => FC::schema($f, $f['author']) . SeoService::breadcrumbs($crumbs)]]);
    }
}
