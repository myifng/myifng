<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Tag;
use App\Models\Topic;
use App\Services\NewsQuery;
use App\Services\SeoService;

/** टॉपिक / विशेष सेक्शन (/topic/{slug}) और टैग (/tag/{slug}) */
final class TopicController extends FrontController
{
    public function topic(Request $request, string $slug): Response
    {
        $t = Topic::firstWhere('slug', $slug);
        if (!$t || $t['status'] !== 'active') {
            throw new HttpException(404);
        }
        [$w, $p] = NewsQuery::topicWhere((int) $t['id']);
        $items = NewsQuery::page($w, $p, $this->page($request->int('page', 1)));
        $crumbs = [['होम', url()], [$t['name'], null]];
        return $this->listing([
            'heading' => $t['name'], 'desc' => $t['description'], 'crumbs' => $crumbs, 'items' => $items,
            'banner' => $t['type'] === 'special' ? ($t['banner'] ?: $t['image']) : null, 'color' => $t['color'], 'special' => $t['type'] === 'special',
            'seo' => [
                'title' => ($t['meta_title'] ?: $t['name']) . ($items->page > 1 ? ' - पेज ' . $items->page : ''),
                'description' => $t['meta_description'] ?: ($t['description'] ?: $t['name'] . ' से जुड़ी सभी ख़बरें।'),
                'canonical' => route('topic', ['slug' => $t['slug']]) . ($items->page > 1 ? '?page=' . $items->page : ''), 'image' => $t['image'],
                'jsonld' => SeoService::breadcrumbs($crumbs),
            ],
        ]);
    }

    public function tag(Request $request, string $slug): Response
    {
        $t = Tag::firstWhere('slug', $slug);
        if (!$t) {
            throw new HttpException(404);
        }
        [$w, $p] = NewsQuery::tagWhere((int) $t['id']);
        $items = NewsQuery::page($w, $p, $this->page($request->int('page', 1)));
        if ($items->total === 0 && $items->page === 1) {
            throw new HttpException(404); // ख़ाली टैग पेज सर्च इंजन में न जाएँ
        }
        $crumbs = [['होम', url()], ['#' . $t['name'], null]];
        return $this->listing([
            'heading' => '#' . $t['name'], 'desc' => $t['description'], 'crumbs' => $crumbs, 'items' => $items,
            'seo' => [
                'title' => ($t['meta_title'] ?: $t['name'] . ' से जुड़ी ख़बरें') . ($items->page > 1 ? ' - पेज ' . $items->page : ''),
                'description' => $t['meta_description'] ?: ($t['description'] ?: $t['name'] . ' से जुड़ी ताज़ा ख़बरें, ' . setting('site_name') . ' पर।'),
                'robots' => setting('seo_noindex_tags', '0') === '1' ? 'noindex,follow' : 'index,follow',
                'canonical' => route('tag', ['slug' => $t['slug']]) . ($items->page > 1 ? '?page=' . $items->page : ''),
                'jsonld' => SeoService::breadcrumbs($crumbs),
            ],
        ]);
    }
}
