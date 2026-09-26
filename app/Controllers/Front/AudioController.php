<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\AudioItem;
use App\Services\EmbedService;
use App\Services\MultimediaService;
use App\Services\NewsQuery;
use App\Services\SeoService;

/** /audio, /audio/{slug}, /podcast/{slug}, /podcast/{slug}/feed (RSS) */
final class AudioController extends FrontController
{
    public function index(Request $request): Response
    {
        $type = $request->str('type');
        $where = isset(AudioItem::TYPES[$type]) ? 'x.type = ?' : '1=1';
        $params = isset(AudioItem::TYPES[$type]) ? [$type] : [];
        if (!$params) {
            $type = '';
        }
        $items = MultimediaService::page('audio', $where, $params, $this->page($request->int('page', 1)), 16);
        $series = $items->page === 1 && $type === '' ? db()->all("SELECT s.*, (SELECT COUNT(*) FROM {p}audio_items x WHERE x.series_id = s.id AND " . MultimediaService::published() . ") AS episodes
            FROM {p}podcast_series s WHERE s.status = 'active' HAVING episodes > 0 ORDER BY s.sort_order, s.title LIMIT 8") : [];
        $chips = [['सभी', route('audio'), $type === ''], ['ऑडियो न्यूज़', route('audio') . '?type=news', $type === 'news'], ['पॉडकास्ट एपिसोड', route('audio') . '?type=episode', $type === 'episode']];
        return $this->view('front/mm-listing', [
            'kind' => 'audio', 'heading' => 'ऑडियो / पॉडकास्ट', 'icon' => 'fa-headphones', 'items' => $items, 'chips' => $chips, 'series' => $series,
            'listHeading' => $series ? 'ताज़ा ऑडियो' : null, 'desc' => 'सुनिए ख़बरें: ऑडियो न्यूज़ और पॉडकास्ट।', 'crumbs' => [['होम', url()], ['ऑडियो', null]],
            'seo' => ['title' => 'ऑडियो और पॉडकास्ट' . ($items->page > 1 ? ' - पेज ' . $items->page : ''), 'canonical' => route('audio') . ($type ? '?type=' . $type : ''),
                'description' => setting('site_name') . ' के ऑडियो न्यूज़ और पॉडकास्ट।'],
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $a = MultimediaService::find('audio', $slug) ?? throw new HttpException(404);
        NewsQuery::hit('audio_items', (int) $a['id']);
        $src = $a['file'] ? upload_url($a['file']) : EmbedService::safeLink($a['external_url']);
        $more = $a['series_id'] ? MultimediaService::list('audio', 'x.series_id = ? AND x.id <> ?', [$a['series_id'], $a['id']], 8, 0, 'x.episode_no DESC, x.published_at DESC')
            : MultimediaService::list('audio', "x.type = 'news' AND x.id <> ?", [$a['id']], 8);
        $news = $a['news_id'] ? db()->first('SELECT n.title, n.slug FROM {p}news n WHERE n.id = ? AND ' . NewsQuery::PUBLISHED, [$a['news_id']]) : null;
        $url = MultimediaService::url('audio', $a);
        $crumbs = [['होम', url()], ['ऑडियो', route('audio')]];
        if ($a['series']) {
            $crumbs[] = [$a['series'], route('podcast', ['slug' => $a['series_slug']])];
        }
        $crumbs[] = [\App\Helpers\Str::limit((string) $a['title'], 60), null];
        $desc = $a['meta_description'] ?: \App\Helpers\Str::limit((string) ($a['description'] ?: $a['title']), 160);
        $schema = ['@context' => 'https://schema.org', '@type' => $a['type'] === 'episode' ? 'PodcastEpisode' : 'AudioObject', 'name' => $a['title'], 'description' => $desc,
            'url' => $url, 'datePublished' => date('c', strtotime($a['published_at']))];
        if ($src) {
            $schema[$a['type'] === 'episode' ? 'associatedMedia' : 'contentUrl'] = $a['type'] === 'episode' ? ['@type' => 'MediaObject', 'contentUrl' => $src] : $src;
        }
        if ($iso = EmbedService::isoDuration($a['duration'] ? (int) $a['duration'] : null)) {
            $schema[$a['type'] === 'episode' ? 'timeRequired' : 'duration'] = $iso;
        }
        if ($a['series']) {
            $schema['partOfSeries'] = ['@type' => 'PodcastSeries', 'name' => $a['series'], 'url' => route('podcast', ['slug' => $a['series_slug']])];
        }
        return $this->view('front/audio', [
            'a' => $a, 'src' => $src, 'more' => $more, 'news' => $news, 'crumbs' => $crumbs, 'shareUrl' => $url, 'side' => $this->sidebar(),
            'seo' => ['title' => $a['meta_title'] ?: $a['title'], 'description' => $desc, 'canonical' => $url, 'image' => mm_thumb_src($a, 'large'),
                'jsonld' => SeoService::json($schema) . SeoService::breadcrumbs($crumbs, $url)],
        ]);
    }

    public function series(Request $request, string $slug): Response
    {
        $s = $this->findSeries($slug);
        $items = MultimediaService::page('audio', 'x.series_id = ?', [$s['id']], $this->page($request->int('page', 1)), 20);
        if (!$items->total) {
            throw new HttpException(404);
        }
        $url = route('podcast', ['slug' => $s['slug']]);
        $feed = route('podcast.feed', ['slug' => $s['slug']]);
        return $this->view('front/mm-listing', [
            'kind' => 'audio', 'heading' => $s['title'], 'icon' => 'fa-podcast', 'items' => $items, 'chips' => [], 'desc' => $s['description'], 'banner' => null,
            'crumbs' => [['होम', url()], ['ऑडियो', route('audio')], [$s['title'], null]], 'listHeading' => 'सभी एपिसोड',
            'actions' => '<a class="btn-outline" href="' . e($feed) . '"><i class="fa-solid fa-rss"></i> RSS फ़ीड</a>',
            'seo' => ['title' => $s['title'] . ' - पॉडकास्ट', 'canonical' => $url, 'description' => $s['description'] ?: $s['title'], 'image' => $s['cover'],
                'jsonld' => SeoService::json(['@context' => 'https://schema.org', '@type' => 'PodcastSeries', 'name' => $s['title'], 'url' => $url, 'webFeed' => $feed,
                    'description' => (string) $s['description'], 'image' => $s['cover'] ? media_url($s['cover'], 'large') : null, 'author' => $s['author'] ? ['@type' => 'Person', 'name' => $s['author']] : null])],
        ]);
    }

    /** पॉडकास्ट RSS 2.0 + iTunes टैग (Apple Podcasts / Spotify) */
    public function feed(Request $request, string $slug): Response
    {
        $s = $this->findSeries($slug);
        $eps = MultimediaService::list('audio', 'x.series_id = ?', [$s['id']], 100);
        $xml = app('view')->render('front/podcast-feed', ['s' => $s, 'eps' => $eps]);
        return (new Response($xml))->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }

    private function findSeries(string $slug): array
    {
        return db()->first("SELECT * FROM {p}podcast_series WHERE slug = ? AND status = 'active'", [$slug]) ?? throw new HttpException(404);
    }
}
