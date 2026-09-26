<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Video;
use App\Services\EmbedService;
use App\Services\MultimediaService;
use App\Services\NewsQuery;
use App\Services\SeoService;

/** /videos (प्रकार के टैब), /videos/playlist/{slug}, /video/{slug} */
final class VideoController extends FrontController
{
    public function index(Request $request): Response
    {
        $type = $request->str('type');
        $where = '1=1';
        $params = [];
        if (isset(Video::TYPES[$type])) {
            $where = 'x.type = ?';
            $params[] = $type;
        } else {
            $type = '';
        }
        $items = MultimediaService::page('video', $where, $params, $this->page($request->int('page', 1)), 16);
        $chips = [['सभी', route('videos'), $type === '']];
        foreach (Video::TYPES as $k => $l) {
            $chips[] = [$k === 'short' ? 'शॉर्ट्स' : $l, route('videos') . '?type=' . $k, $type === $k];
        }
        $playlists = $type === '' && $items->page === 1 ? db()->all("SELECT p.name, p.slug, p.type, p.cover FROM {p}video_playlists p WHERE p.status = 'active'
            AND EXISTS (SELECT 1 FROM {p}videos x WHERE x.playlist_id = p.id AND " . MultimediaService::published() . ') ORDER BY p.sort_order, p.name LIMIT 12') : [];
        $heading = $type ? ($type === 'short' ? 'शॉर्ट्स' : Video::TYPES[$type]) : 'वीडियो';
        return $this->view('front/mm-listing', [
            'kind' => 'video', 'heading' => $heading, 'icon' => 'fa-circle-play', 'items' => $items, 'chips' => $chips, 'tall' => $type === 'short',
            'desc' => 'ताज़ा वीडियो ख़बरें, इंटरव्यू, ग्राउंड रिपोर्ट और शो।', 'playlists' => $playlists,
            'crumbs' => [['होम', url()], ['वीडियो', $type ? route('videos') : null]] + ($type ? [2 => [$heading, null]] : []),
            'seo' => ['title' => $heading . ($items->page > 1 ? ' - पेज ' . $items->page : ''), 'canonical' => route('videos') . ($type ? '?type=' . $type : '') . ($items->page > 1 ? ($type ? '&' : '?') . 'page=' . $items->page : ''),
                'description' => setting('site_name') . ' के ताज़ा वीडियो: ख़बरें, इंटरव्यू, ग्राउंड रिपोर्ट और शो।'],
        ]);
    }

    public function playlist(Request $request, string $slug): Response
    {
        $pl = db()->first("SELECT * FROM {p}video_playlists WHERE slug = ? AND status = 'active'", [$slug]) ?? throw new HttpException(404);
        $items = MultimediaService::page('video', 'x.playlist_id = ?', [$pl['id']], $this->page($request->int('page', 1)), 16);
        if (!$items->total) {
            throw new HttpException(404);
        }
        return $this->view('front/mm-listing', [
            'kind' => 'video', 'heading' => $pl['name'], 'icon' => $pl['type'] === 'show' ? 'fa-tv' : 'fa-list', 'items' => $items, 'chips' => [], 'tall' => false,
            'desc' => $pl['description'], 'banner' => $pl['cover'], 'playlists' => [],
            'crumbs' => [['होम', url()], ['वीडियो', route('videos')], [$pl['name'], null]],
            'seo' => ['title' => $pl['name'], 'canonical' => route('videos.playlist', ['slug' => $pl['slug']]), 'description' => $pl['description'] ?: $pl['name'] . ': सभी वीडियो', 'image' => $pl['cover']],
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $v = MultimediaService::find('video', $slug) ?? throw new HttpException(404);
        NewsQuery::hit('videos', (int) $v['id']);
        $player = match ($v['source']) {
            'youtube' => ($s = EmbedService::youtubeEmbed($v['source_url'])) ? ['type' => 'iframe', 'src' => $s] : null,
            'embed' => ($s = EmbedService::iframeSrc($v['source_url'])) ? ['type' => 'iframe', 'src' => $s, 'sandbox' => true] : null,
            'upload' => $v['file'] ? ['type' => 'video', 'src' => upload_url($v['file'])] : null,
            default => null,
        };
        $more = $v['playlist_id'] ? MultimediaService::list('video', 'x.playlist_id = ? AND x.id <> ?', [$v['playlist_id'], $v['id']], 8) : [];
        $exclude = array_merge([(int) $v['id']], array_column($more, 'id'));
        $related = MultimediaService::list('video', ($v['category_id'] ? 'x.category_id = ' . (int) $v['category_id'] . ' AND ' : '') . 'x.id NOT IN (' . implode(',', array_map('intval', $exclude)) . ')', [], 8);
        if (count($related) < 4) {
            $related = array_merge($related, MultimediaService::list('video', 'x.id NOT IN (' . implode(',', array_map('intval', array_merge($exclude, array_column($related, 'id')))) . ')', [], 8 - count($related)));
        }
        $url = MultimediaService::url('video', $v);
        $thumb = mm_thumb_src($v, 'large');
        $crumbs = [['होम', url()], ['वीडियो', route('videos')]];
        if ($v['playlist']) {
            $crumbs[] = [$v['playlist'], route('videos.playlist', ['slug' => $v['playlist_slug']])];
        }
        $crumbs[] = [\App\Helpers\Str::limit((string) $v['title'], 60), null];
        $desc = $v['meta_description'] ?: \App\Helpers\Str::limit((string) ($v['description'] ?: $v['title']), 160);
        $schema = ['@context' => 'https://schema.org', '@type' => 'VideoObject', 'name' => $v['title'], 'description' => $desc ?: $v['title'],
            'thumbnailUrl' => $thumb ? [$thumb] : [upload_url((string) setting('logo'))], 'uploadDate' => date('c', strtotime($v['published_at'])), 'url' => $url];
        if ($iso = EmbedService::isoDuration($v['duration'] ? (int) $v['duration'] : null)) {
            $schema['duration'] = $iso;
        }
        if ($player) {
            $schema[$player['type'] === 'video' ? 'contentUrl' : 'embedUrl'] = $player['src'];
        }
        return $this->view('front/video', [
            'v' => $v, 'player' => $player, 'more' => $more, 'related' => array_slice($related, 0, 8), 'crumbs' => $crumbs, 'shareUrl' => $url, 'side' => $this->sidebar(),
            'seo' => ['title' => $v['meta_title'] ?: $v['title'], 'description' => $desc, 'canonical' => $url, 'image' => $thumb, 'og_type' => 'video.other',
                'jsonld' => SeoService::json($schema) . SeoService::breadcrumbs($crumbs, $url)],
        ]);
    }
}
