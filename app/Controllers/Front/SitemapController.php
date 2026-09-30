<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Services\SitemapService;

/** साइटमैप, robots.txt, RSS फ़ीड */
final class SitemapController extends Controller
{
    private function xml(string $body, string $type = 'application/xml'): Response
    {
        return (new Response($body, 200))->header('Content-Type', $type . '; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex, follow')->header('Cache-Control', 'public, max-age=300');
    }

    public function index(Request $request): Response
    {
        return $this->xml(SitemapService::index());
    }

    public function part(Request $request, string $part): Response
    {
        $body = match (true) {
            $part === 'news' => setting('sitemap_news', '1') === '1' ? SitemapService::news() : null,
            $part === 'pages' => SitemapService::pages(),
            $part === 'sections' => SitemapService::sections(),
            $part === 'videos' => setting('sitemap_videos', '1') === '1' ? SitemapService::videos() : null,
            $part === 'images' => setting('sitemap_images', '1') === '1' ? SitemapService::images() : null,
            $part === 'stories' => SitemapService::stories(),
            (bool) preg_match('/^posts-(\d{4}-\d{2})(?:-(\d+))?$/', $part, $m) => SitemapService::posts($m[1], (int) ($m[2] ?? 1)),
            default => null,
        };
        if ($body === null) {
            throw new HttpException(404);
        }
        return $this->xml($body);
    }

    public function robots(Request $request): Response
    {
        return (new Response(SitemapService::robots(), 200))->header('Content-Type', 'text/plain; charset=UTF-8')->header('Cache-Control', 'public, max-age=3600');
    }

    public function feed(Request $request): Response
    {
        return $this->xml(SitemapService::feed(), 'application/rss+xml');
    }

    public function categoryFeed(Request $request, string $slug): Response
    {
        $cat = Category::firstWhere('slug', $slug);
        if (!$cat || $cat['status'] !== 'active') {
            throw new HttpException(404);
        }
        return $this->xml(SitemapService::feed($cat), 'application/rss+xml');
    }
}
