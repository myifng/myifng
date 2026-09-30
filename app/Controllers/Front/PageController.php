<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Page;
use App\Services\ContentRenderer;

/** सार्वजनिक पेज: /page/{slug} */
final class PageController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $page = Page::findBySlug($slug);
        if (!$page || ($page['status'] !== 'published' && !auth()->check())) {
            throw new HttpException(404);
        }
        return $this->render($page, $page['status'] !== 'published');
    }

    /** एडमिन प्रीव्यू भी यही इस्तेमाल करता है */
    public function render(array $page, bool $isPreview): Response
    {
        $content = ContentRenderer::render($page['content']);
        $content = $isPreview ? $content : \App\Services\AdService::shortcodes($content);
        $content = \App\Services\PollService::shortcodes($content);
        return $this->view('front/page', [
            'page' => $page,
            'content' => $content,
            'isPreview' => $isPreview,
            'seo' => [
                'title' => $page['meta_title'] ?: $page['title'],
                'description' => $page['meta_description'] ?: ($page['excerpt'] ?: \App\Helpers\Str::limit(strip_tags($content), 160)),
                'keywords' => $page['meta_keywords'],
                'image' => $page['og_image'] ?: $page['featured_image'],
                'robots' => $isPreview ? 'noindex,nofollow' : $page['robots'],
                'canonical' => url('page/' . $page['slug']),
            ],
            'layoutOptions' => ['header' => (bool) $page['show_header'], 'footer' => (bool) $page['show_footer']],
        ]);
    }
}
