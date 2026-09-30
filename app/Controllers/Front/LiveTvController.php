<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\LiveTvService;
use App\Services\MultimediaService;
use App\Services\SeoService;

/** /live-tv (डिफ़ॉल्ट चैनल) और /live-tv/{slug} */
final class LiveTvController extends FrontController
{
    public function show(Request $request, ?string $slug = null): Response
    {
        $channel = $slug !== null ? LiveTvService::find($slug) : LiveTvService::main();
        if ($slug !== null && !$channel) {
            throw new HttpException(404);
        }
        $player = $channel ? LiveTvService::player($channel, true) : null;
        $today = $channel ? LiveTvService::today((int) $channel['id']) : [];
        $now = null;
        foreach ($today as $p) {
            if ($p['now']) {
                $now = $p;
            }
        }
        $canonical = $channel && $slug !== null ? route('live_tv.channel', ['slug' => $channel['slug']]) : route('live_tv');
        $title = $channel ? $channel['name'] . ($channel['is_live'] ? ' · लाइव' : '') : 'लाइव टीवी';
        \App\Services\AnalyticsService::context(['type' => 'live']);
        return $this->view('front/live-tv', [
            'channel' => $channel, 'player' => $player, 'today' => $today, 'now' => $now, 'channels' => LiveTvService::channels(),
            'videos' => MultimediaService::list('video', '1=1', [], 8),
            'seo' => [
                'title' => $title, 'canonical' => $canonical,
                'description' => $channel['description'] ?? ('देखें ' . setting('site_name') . ' लाइव टीवी: ताज़ा ख़बरें, बहस और ख़ास कार्यक्रम।'),
                'image' => $channel['logo'] ?? null, 'robots' => $player ? 'index,follow' : 'noindex,follow',
                'jsonld' => $player ? SeoService::json(['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => $title, 'url' => $canonical,
                    'mainEntity' => ['@type' => 'VideoObject', 'name' => $title, 'description' => $channel['description'] ?: $title, 'embedUrl' => $player['src'],
                        'thumbnailUrl' => $channel['logo'] ? media_url($channel['logo'], 'large') : (setting('logo') ? upload_url((string) setting('logo')) : url()),
                        'uploadDate' => date('c', strtotime((string) ($channel['created_at'] ?? 'today'))),
                        'publication' => ['@type' => 'BroadcastEvent', 'isLiveBroadcast' => (bool) $channel['is_live']]]]) : '',
            ],
        ]);
    }
}
