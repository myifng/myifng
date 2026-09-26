<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\MultimediaService;
use App\Services\NewsQuery;
use App\Services\SeoService;

/** /photos, /photos/{slug} (फ़ुलस्क्रीन गैलरी) */
final class GalleryController extends FrontController
{
    public function index(Request $request): Response
    {
        $items = MultimediaService::page('gallery', '1=1', [], $this->page($request->int('page', 1)), 16);
        return $this->view('front/mm-listing', [
            'kind' => 'gallery', 'heading' => 'फ़ोटो गैलरी', 'icon' => 'fa-images', 'items' => $items, 'chips' => [],
            'desc' => 'तस्वीरों में ख़बरें: देश, प्रदेश और आपके शहर की ख़ास तस्वीरें।', 'crumbs' => [['होम', url()], ['फ़ोटो गैलरी', null]],
            'seo' => ['title' => 'फ़ोटो गैलरी' . ($items->page > 1 ? ' - पेज ' . $items->page : ''), 'canonical' => route('galleries') . ($items->page > 1 ? '?page=' . $items->page : ''),
                'description' => setting('site_name') . ' की फ़ोटो गैलरी: तस्वीरों में ताज़ा ख़बरें।'],
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $g = MultimediaService::find('gallery', $slug) ?? throw new HttpException(404);
        NewsQuery::hit('galleries', (int) $g['id']);
        $photos = db()->all('SELECT * FROM {p}gallery_photos WHERE gallery_id = ? ORDER BY sort_order, id', [$g['id']]);
        $loc = $g['location_id'] ? db()->first('SELECT name, path FROM {p}locations WHERE id = ?', [$g['location_id']]) : null;
        $related = MultimediaService::list('gallery', 'x.id <> ?', [$g['id']], 4);
        $url = MultimediaService::url('gallery', $g);
        $crumbs = [['होम', url()], ['फ़ोटो गैलरी', route('galleries')], [\App\Helpers\Str::limit((string) $g['title'], 60), null]];
        $desc = $g['meta_description'] ?: \App\Helpers\Str::limit((string) ($g['description'] ?: $g['title']), 160);
        $schema = ['@context' => 'https://schema.org', '@type' => 'ImageGallery', 'name' => $g['title'], 'description' => $desc, 'url' => $url,
            'datePublished' => date('c', strtotime($g['published_at'])),
            'image' => array_map(static fn($p) => array_filter(['@type' => 'ImageObject', 'contentUrl' => media_url($p['image'], 'large'), 'caption' => $p['caption'],
                'creditText' => $p['credit'] ?: $p['photographer'], 'copyrightNotice' => $p['copyright'], 'creator' => $p['photographer'] ? ['@type' => 'Person', 'name' => $p['photographer']] : null]), array_slice($photos, 0, 50))];
        return $this->view('front/gallery', [
            'g' => $g, 'photos' => $photos, 'loc' => $loc, 'related' => $related, 'crumbs' => $crumbs, 'shareUrl' => $url,
            'seo' => ['title' => $g['meta_title'] ?: $g['title'], 'description' => $desc, 'canonical' => $url, 'image' => $g['cover'], 'og_type' => 'article',
                'published' => $g['published_at'], 'modified' => $g['updated_at'], 'jsonld' => SeoService::json($schema) . SeoService::breadcrumbs($crumbs, $url)],
        ]);
    }
}
