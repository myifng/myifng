<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Location;
use App\Services\AdService;
use App\Services\ContentRenderer;
use App\Services\LiveBlogService;
use App\Services\NewsQuery;
use App\Services\NewsService;
use App\Services\NewsWorkflow;
use App\Services\SeoService;

/** ख़बर का पेज: /news/{slug}; एडमिन प्रीव्यू भी यही render() इस्तेमाल करता है */
final class NewsController extends FrontController
{
    public function show(Request $request, string $slug): Response
    {
        $news = db()->first('SELECT n.* FROM {p}news n WHERE n.slug = ? AND ' . NewsQuery::PUBLISHED, [$slug]);
        if (!$news) {
            throw new HttpException(404);
        }
        NewsQuery::countView($news);
        return $this->render($news, false);
    }

    public function render(array $news, bool $isPreview): Response
    {
        $content = ContentRenderer::render($news['content']);
        $rel = NewsService::relations((int) $news['id']);
        $cat = $news['category_id'] ? Category::find((int) $news['category_id']) : null;
        $parentCat = $cat && $cat['parent_id'] ? Category::find((int) $cat['parent_id']) : null;
        $loc = $news['location_id'] ? Location::find((int) $news['location_id']) : null;
        $chain = $loc ? array_values(array_filter([...Location::ancestors($loc), $loc], static fn($l) => $l['type'] !== 'country')) : [];
        $reporter = $news['reporter_id'] ? db()->first('SELECT id, name, avatar, bio FROM {p}users WHERE id = ?', [$news['reporter_id']]) : null;
        $crumbs = [['होम', url()]];
        foreach (array_filter([$parentCat, $cat]) as $c) {
            $crumbs[] = [$c['name'], route('category', ['slug' => $c['slug']])];
        }
        $crumbs[] = [\App\Helpers\Str::limit((string) $news['title'], 60), null];
        $related = setting('related_news', '1') === '1' ? NewsQuery::related($news, 6) : [];
        // विज्ञापन: इस ख़बर की श्रेणी/लोकेशन (ऊपर वाली भी) के हिसाब से; सामग्री में [ad:…] और बीच का विज्ञापन
        AdService::setContext(array_filter([$cat['id'] ?? null, $parentCat['id'] ?? null]), array_column($chain, 'id'));
        $plain = \App\Helpers\Str::limit(strip_tags($content), 160); // विज्ञापन जुड़ने से पहले
        if (!$isPreview) {
            $content = AdService::inject(AdService::shortcodes($content));
        }
        $content = \App\Services\PollService::shortcodes($content); // Phase 11: [poll:ID]
        // Phase 11: पाठक (सेव/फ़ॉलो/इतिहास), टिप्पणियाँ
        $reader = \App\Services\ReaderAuth::user();
        $engage = ['reader' => $reader, 'bookmarked' => false, 'follows' => []];
        if ($reader && !$isPreview) {
            \App\Services\ReaderService::logRead($reader, (int) $news['id']);
            $engage['bookmarked'] = \App\Services\ReaderService::isBookmarked((int) $reader['id'], (int) $news['id']);
            $engage['follows'] = \App\Services\ReaderService::follows((int) $reader['id']);
        }
        [$cOpen, $cWhy] = \App\Services\CommentService::open($news);
        [$cThread, $cCount] = $isPreview ? [[], 0] : \App\Services\CommentService::thread((int) $news['id']);
        $engage['comments'] = ['open' => $cOpen && !$isPreview, 'why' => $cWhy, 'thread' => $cThread, 'count' => $cCount, 'enabled' => setting('comments_enabled', '1') === '1' && (int) ($news['allow_comments'] ?? 1)];
        $faq = SeoService::faqItems($news['faq'] ?? null);
        $live = LiveBlogService::forNews((int) $news['id']);
        $liveUpdates = $live ? LiveBlogService::updates((int) $live['id']) : [];
        return $this->view('front/article', [
            'news' => $news, 'content' => $content, 'isPreview' => $isPreview, 'rel' => $rel, 'category' => $cat, 'locationChain' => $chain,
            'reporter' => $reporter, 'crumbs' => $crumbs, 'related' => $related, 'side' => $this->sidebar(), 'live' => $live, 'liveUpdates' => $liveUpdates,
            'previewNote' => $isPreview ? 'प्रीव्यू · स्थिति: ' . NewsWorkflow::label($news['status']) . ($news['deleted_at'] ? ' (ट्रैश में)' : '') : '',
            'shareUrl' => NewsService::url($news), 'faq' => $faq, 'engage' => $engage,
            'seo' => [
                'title' => $news['meta_title'] ?: $news['title'],
                'description' => $news['meta_description'] ?: ($news['summary'] ?: $plain),
                'keywords' => $news['meta_keywords'] ?: implode(', ', $rel['tags']),
                'image' => $news['featured_image'] ? media_url($news['featured_image'], 'large') : null,
                'og_title' => $news['og_title'] ?? null, 'og_description' => $news['og_description'] ?? null, 'og_image' => ($news['og_image'] ?? '') ?: null,
                'robots' => $isPreview ? 'noindex,nofollow' : $news['robots'],
                'canonical' => $news['canonical_url'] ?: NewsService::url($news),
                'og_type' => 'article',
                'published' => $news['published_at'], 'modified' => $news['corrected_at'] ?: $news['updated_at'], 'section' => $cat['name'] ?? null,
                'jsonld' => $isPreview ? '' : SeoService::article($news, $reporter['name'] ?? null, $cat['name'] ?? null, $rel['tags']) . SeoService::breadcrumbs($crumbs, NewsService::url($news))
                    . ($live ? LiveBlogService::schema($news, $live, $liveUpdates) : '') . SeoService::faq($faq),
            ],
        ]);
    }
}
