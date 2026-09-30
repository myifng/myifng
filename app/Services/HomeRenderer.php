<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Location;

/**
 * होमपेज: होमपेज बिल्डर के चालू सेक्शन → HTML (कुछ भी हार्डकोड नहीं)।
 * हर सेक्शन का डेटा यहाँ, दिखावट app/Views/front/blocks/{type}.php में।
 * सेक्शन का HTML 5 मिनट कैश; ख़बर प्रकाशित/बदलने पर 'home' कैश साफ़। "मेरा शहर" वाला सेक्शन हर पाठक का अलग, कैश नहीं।
 */
final class HomeRenderer
{
    private const TTL = 300;

    public static function render(): string
    {
        $html = '';
        $halfBuffer = [];
        foreach (HomepageService::sections(true) as $s) {
            $block = HomepageService::block($s['block_type']);
            if (!$block || HomepageService::readiness($block) !== null) {
                continue; // मॉड्यूल अभी तैयार नहीं
            }
            // पाठक का शहर हर पाठक का अलग; ब्रेकिंग अपने समय पर ख़त्म होती है (उसका अपना 60 सेकंड कैश)
            $dynamic = ($s['block_type'] === 'location' && empty($s['settings']['location'])) || in_array($s['block_type'], ['breaking', 'ads', 'newsletter', 'poll'], true); // विज्ञापन: बारी-बारी; फ़ॉर्म में हर पाठक का अपना CSRF टोकन/वोट
            $key = 'home.section.' . $s['id'] . '.' . md5($s['updated_at'] . json_encode($s['settings']));
            $inner = $dynamic ? self::section($s) : cache()->remember($key, self::TTL, static fn() => self::section($s));
            if ($inner === '') {
                continue;
            }
            $cls = 'hs hs-' . $s['block_type'] . (!$s['show_desktop'] ? ' hide-desktop' : '') . (!$s['show_mobile'] ? ' hide-mobile' : '');
            $wrapped = '<section class="' . e($cls) . '" aria-label="' . e($s['title'] ?: $block['label']) . '">' . $inner . '</section>';
            // "आधी चौड़ाई" वाले लगातार श्रेणी सेक्शन दो-दो की पंक्ति में
            if ($s['block_type'] === 'category' && ($s['settings']['layout'] ?? '') === 'half') {
                $halfBuffer[] = $wrapped;
                if (count($halfBuffer) === 2) {
                    $html .= '<div class="two">' . implode('', $halfBuffer) . '</div>';
                    $halfBuffer = [];
                }
                continue;
            }
            if ($halfBuffer) {
                $html .= '<div class="two">' . implode('', $halfBuffer) . '</div>';
                $halfBuffer = [];
            }
            $html .= $wrapped;
        }
        if ($halfBuffer) {
            $html .= '<div class="two">' . implode('', $halfBuffer) . '</div>';
        }
        return $html;
    }

    /** होमपेज पर बैनर वाला ब्रेकिंग सेक्शन है? (तब ऊपर अपने आप वाला अलर्ट बैनर नहीं) */
    public static function hasBreakingBanner(): bool
    {
        foreach (HomepageService::sections(true) as $s) {
            if ($s['block_type'] === 'breaking' && ($s['settings']['style'] ?? 'list') !== 'list') {
                return true;
            }
        }
        return false;
    }

    /** एक सेक्शन का HTML (डेटा न हो तो '') */
    public static function section(array $s): string
    {
        $data = self::data($s['block_type'], $s['settings'], (string) $s['title']);
        if ($data === null) {
            return '';
        }
        return app('view')->render('front/blocks/' . $s['block_type'], $data + ['title' => $s['title'], 'set' => $s['settings']]);
    }

    /** ख़बरें: हाथ से चुनी या अपने आप (श्रेणी/फ़्लैग के हिसाब से) */
    private static function stories(array $set, int $count, ?string $flag = null): array
    {
        if (($set['source'] ?? 'auto') === 'manual' && !empty($set['stories'])) {
            return array_slice(NewsQuery::byIds((array) $set['stories']), 0, $count);
        }
        if (!empty($set['category'])) {
            [$w, $p] = NewsQuery::categoryWhere((int) $set['category']);
            return NewsQuery::list($w, $p, $count);
        }
        if ($flag) {
            $rows = NewsQuery::flagged($flag, $count);
            if (count($rows) < $count) {
                $rows = array_merge($rows, NewsQuery::latest($count - count($rows), array_column($rows, 'id')));
            }
            return $rows;
        }
        return NewsQuery::latest($count);
    }

    private static function data(string $type, array $set, string $title): ?array
    {
        $count = (int) ($set['count'] ?? 6);
        switch ($type) {
            case 'hero':
                $flag = ['featured' => 'is_featured', 'editor_pick' => 'is_editor_pick'][$set['filter'] ?? ''] ?? null;
                $items = self::stories($set, max(3, $count), $flag);
                if (!$items) {
                    return null;
                }
                $ids = array_column($items, 'id');
                return ['items' => $items, 'latest' => NewsQuery::latest(8, $ids), 'popular' => NewsQuery::mostRead(7, 5)];
            case 'grid':
            case 'slider':
                $items = self::stories($set, $count);
                if (!$items) {
                    return null;
                }
                $cat = !empty($set['category']) ? Category::find((int) $set['category']) : null;
                return ['items' => $items, 'more' => $cat && !empty($set['more']) ? route('category', ['slug' => $cat['slug']]) : null, 'heading' => $title ?: ($cat['name'] ?? '')];
            case 'latest':
                $items = NewsQuery::latest($count);
                return $items ? ['items' => $items, 'more' => !empty($set['more']) ? route('latest') : null] : null;
            case 'category':
                $cat = !empty($set['category']) ? Category::find((int) $set['category']) : null;
                if (!$cat || $cat['status'] !== 'active') {
                    return null;
                }
                [$w, $p] = NewsQuery::categoryWhere((int) $cat['id']);
                $items = NewsQuery::list($w, $p, $count);
                if (!$items) {
                    return null;
                }
                $subs = db()->all("SELECT name, slug FROM {p}categories WHERE parent_id = ? AND status = 'active' ORDER BY sort_order LIMIT 6", [$cat['id']]);
                return ['items' => $items, 'cat' => $cat, 'subs' => $subs, 'heading' => $title ?: $cat['name'], 'more' => !empty($set['more']) ? route('category', ['slug' => $cat['slug']]) : null];
            case 'location':
                return self::locationData($set, $count, $title);
            case 'trending':
                $items = TrendingService::trending($count);
                $tags = NewsQuery::trending(12);
                return ($items || $tags) ? ['items' => $items, 'tags' => $tags] : null;
            case 'most_read':
                // Phase 13: पढ़ी/शेयर, श्रेणी या शहर के हिसाब से लोकप्रिय
                $cat = !empty($set['category']) ? (int) $set['category'] : null;
                $loc = !empty($set['location']) ? (int) $set['location'] : null;
                $items = ($set['metric'] ?? 'read') === 'shared'
                    ? TrendingService::mostShared((int) ($set['period'] ?? 7), $count, $cat, $loc)
                    : TrendingService::mostRead((int) ($set['period'] ?? 7), $count, $cat, $loc);
                return $items ? ['items' => $items] : null;
            case 'live_tv':
                $ch = LiveTvService::main();
                $player = $ch ? LiveTvService::player($ch, !empty($set['autoplay'])) : null;
                return $player ? ['channel' => $ch, 'player' => $player, 'now' => LiveTvService::current((int) $ch['id'])] : null;
            case 'videos':
                [$w, $p] = self::mmWhere($set, ['featured' => 'x.is_featured = 1', 'short' => "x.type = 'short'", 'interview' => "x.type = 'interview'", 'ground_report' => "x.type = 'ground_report'", 'show' => "x.type = 'show'"]);
                if (($set['layout'] ?? '') === 'shorts' && ($set['filter'] ?? '') === '') {
                    $w .= " AND x.type = 'short'";
                }
                $items = MultimediaService::list('video', $w, $p, $count);
                return $items ? ['items' => $items, 'more' => !empty($set['more']) ? route('videos') . (($set['layout'] ?? '') === 'shorts' ? '?type=short' : '') : null] : null;
            case 'gallery':
                $items = MultimediaService::list('gallery', '1=1', [], $count);
                return $items ? ['items' => $items, 'more' => !empty($set['more']) ? route('galleries') : null] : null;
            case 'web_stories':
                $items = MultimediaService::list('story', '1=1', [], $count);
                return $items ? ['items' => $items, 'more' => !empty($set['more']) ? route('stories') : null] : null;
            case 'audio':
                [$w, $p] = self::mmWhere($set, ['news' => "x.type = 'news'", 'episode' => "x.type = 'episode'"]);
                $items = MultimediaService::list('audio', $w, $p, $count);
                return $items ? ['items' => $items, 'more' => !empty($set['more']) ? route('audio') : null] : null;
            case 'breaking':
                if (($set['style'] ?? 'list') !== 'list') { // बैनर (पुराना 'ticker' मान भी): होमपेज अलर्ट वाले आइटम
                    return BreakingService::banner() ? ['banner' => true] : null;
                }
                $items = BreakingService::active();
                return $items ? ['items' => $items, 'banner' => false] : null;
            case 'ads':
                return AdService::enabled() && AdService::pick((string) ($set['slot'] ?? '')) ? [] : null;
            case 'epaper':
                if (!EpaperService::enabled()) {
                    return null;
                }
                $cards = [];
                foreach (EpaperService::editions() as $e) {
                    if ($i = EpaperService::latest((int) $e['id'])) {
                        $cards[] = ['edition' => $e, 'issue' => $i];
                    }
                }
                return $cards ? ['cards' => array_slice($cards, 0, 6)] : null;
            case 'custom_html':
                return trim((string) ($set['html'] ?? '')) !== '' ? [] : null;
            case 'newsletter':
                return NewsletterService::enabled() ? ['lists' => db()->all('SELECT id, name FROM {p}newsletter_lists WHERE is_public = 1 ORDER BY is_default DESC, name')] : null;
            case 'poll':
                $pid = (int) ($set['poll'] ?? 0) ?: (int) db()->value("SELECT id FROM {p}polls WHERE status = 'active' AND (start_at IS NULL OR start_at <= NOW()) AND (end_at IS NULL OR end_at > NOW()) ORDER BY id DESC LIMIT 1");
                $w = $pid ? PollService::widget($pid) : '';
                return $w !== '' ? ['widget' => $w] : null;
            default:
                return null; // विज्ञापन (Phase 9), न्यूज़लेटर (Phase 11) आदि
        }
    }

    /** वीडियो/ऑडियो ब्लॉक: फ़िल्टर + श्रेणी */
    private static function mmWhere(array $set, array $filters): array
    {
        $w = $filters[$set['filter'] ?? ''] ?? '1=1';
        $p = [];
        if (!empty($set['category'])) {
            $w .= ' AND x.category_id IN (' . implode(',', array_map('intval', array_merge([(int) $set['category']], array_column(db()->all('SELECT id FROM {p}categories WHERE parent_id = ?', [(int) $set['category']]), 'id')))) . ')';
        }
        return [$w, $p];
    }

    /** लोकेशन सेक्शन: चुनी लोकेशन (या पाठक का शहर) + नीचे वाली लोकेशन के टैब */
    private static function locationData(array $set, int $count, string $title): ?array
    {
        $loc = !empty($set['location']) ? Location::find((int) $set['location']) : (my_city() ? Location::find((int) my_city()['id']) : null);
        if (!$loc || $loc['status'] !== 'active') {
            // पाठक ने शहर नहीं चुना: लोकप्रिय शहर दिखाकर चुनने को कहें
            $popular = db()->all("SELECT name, path FROM {p}locations WHERE is_popular = 1 AND status = 'active' AND path IS NOT NULL ORDER BY FIELD(type,'state','district','city'), sort_order LIMIT 14");
            return ['loc' => null, 'popular' => $popular, 'heading' => $title ?: 'मेरा शहर'];
        }
        [$w, $p] = NewsQuery::locationWhere((int) $loc['id']);
        $tabs = [['id' => 0, 'name' => 'सभी', 'items' => NewsQuery::list($w, $p, $count), 'url' => $loc['path'] ? url($loc['path']) : null]];
        if (($set['layout'] ?? 'tabs') === 'tabs') {
            $children = db()->all("SELECT id, name, path FROM {p}locations WHERE parent_id = ? AND status = 'active' AND path IS NOT NULL ORDER BY is_popular DESC, sort_order LIMIT 30", [$loc['id']]);
            foreach ($children as $c) {
                [$cw, $cp] = NewsQuery::locationWhere((int) $c['id']);
                $items = NewsQuery::list($cw, $cp, $count);
                if ($items) {
                    $tabs[] = ['id' => (int) $c['id'], 'name' => $c['name'], 'items' => $items, 'url' => url($c['path'])];
                }
                if (count($tabs) >= 9) {
                    break;
                }
            }
        }
        if (!$tabs[0]['items']) {
            return null;
        }
        return ['loc' => $loc, 'tabs' => $tabs, 'heading' => $title ?: $loc['name'], 'mine' => empty($set['location'])];
    }
}
