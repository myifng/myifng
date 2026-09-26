<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Category;
use App\Models\Location;
use App\Services\ContentRenderer;
use App\Services\NewsService;
use App\Services\NewsWorkflow;

/**
 * ख़बर का पेज। Phase 4 में सिर्फ़ एडमिन प्रीव्यू; सार्वजनिक रूट (/news/{slug}) और पूरा डिज़ाइन Phase 5 में।
 */
final class NewsController extends Controller
{
    public function render(array $news, bool $isPreview): Response
    {
        $content = ContentRenderer::render($news['content']);
        $rel = NewsService::relations((int) $news['id']);
        $loc = $news['location_id'] ? Location::find((int) $news['location_id']) : null;
        return $this->view('front/article', [
            'news' => $news, 'content' => $content, 'isPreview' => $isPreview, 'rel' => $rel,
            'category' => $news['category_id'] ? Category::find((int) $news['category_id']) : null,
            'locationChain' => $loc ? [...Location::ancestors($loc), $loc] : [],
            'reporter' => $news['reporter_id'] ? db()->first('SELECT name, avatar FROM {p}users WHERE id = ?', [$news['reporter_id']]) : null,
            'previewNote' => $isPreview ? 'प्रीव्यू · स्थिति: ' . NewsWorkflow::label($news['status']) . ($news['deleted_at'] ? ' (ट्रैश में)' : '') : '',
            'seo' => [
                'title' => $news['meta_title'] ?: $news['title'],
                'description' => $news['meta_description'] ?: ($news['summary'] ?: \App\Helpers\Str::limit(strip_tags($content), 160)),
                'keywords' => $news['meta_keywords'] ?: implode(', ', $rel['tags']),
                'image' => $news['featured_image'],
                'robots' => $isPreview ? 'noindex,nofollow' : $news['robots'],
                'canonical' => $news['canonical_url'] ?: NewsService::url($news),
                'og_type' => 'article',
            ],
        ]);
    }
}
