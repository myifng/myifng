<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Ad;

/**
 * विज्ञापन दिखाना: चालू विज्ञापन (तारीख़, स्थिति, कैप, कैंपेन, बजट) एक बार लोड (60 सेकंड कैश),
 * फिर हर स्लॉट के लिए टार्गेटिंग (श्रेणी/लोकेशन), प्राथमिकता और बारी। डिवाइस: CSS क्लास से (पेज कैश होने पर भी सही)।
 * इम्प्रेशन: पाठक की स्क्रीन पर दिखने पर JS भेजता है; क्लिक: /ad/{id}/click से।
 */
final class AdService
{
    private static ?array $live = null;
    private static array $context = ['categories' => [], 'locations' => []];

    public static function enabled(): bool
    {
        return setting('ads_enabled', '1') === '1';
    }

    /** पेज की श्रेणी/लोकेशन (ऊपर वाली भी) — कंट्रोलर सेट करता है */
    public static function setContext(array $categoryIds = [], array $locationIds = []): void
    {
        self::$context = ['categories' => array_values(array_unique(array_map('intval', array_filter($categoryIds)))),
            'locations' => array_values(array_unique(array_map('intval', array_filter($locationIds))))];
    }

    /** @return array<string, array<int, array>> slot_key => विज्ञापन */
    private static function live(): array
    {
        if (self::$live !== null) {
            return self::$live;
        }
        return self::$live = cache()->remember('ads.live', 60, static function (): array {
            $rows = db()->all("SELECT a.*, s.slot_key, s.max_ads, c.pricing, c.rate, c.budget
                FROM {p}ads a JOIN {p}ad_placements p ON p.ad_id = a.id JOIN {p}ad_slots s ON s.id = p.slot_id AND s.status = 'active'
                LEFT JOIN {p}ad_campaigns c ON c.id = a.campaign_id
                WHERE a.status = 'active' AND (a.start_at IS NULL OR a.start_at <= NOW()) AND (a.end_at IS NULL OR a.end_at > NOW())
                  AND (a.max_impressions IS NULL OR a.impressions < a.max_impressions) AND (a.max_clicks IS NULL OR a.clicks < a.max_clicks)
                  AND (a.campaign_id IS NULL OR (c.status = 'active' AND (c.start_date IS NULL OR c.start_date <= CURDATE()) AND (c.end_date IS NULL OR c.end_date >= CURDATE())))");
            $spent = self::campaignSpend();
            $out = [];
            foreach ($rows as $r) {
                if ($r['campaign_id'] && (float) $r['budget'] > 0 && ($spent[(int) $r['campaign_id']] ?? 0) >= (float) $r['budget']) {
                    continue; // बजट ख़त्म
                }
                unset($r['created_by'], $r['updated_by']);
                $out[$r['slot_key']][] = $r;
            }
            return $out;
        });
    }

    /** CPM/CPC कैंपेन का अब तक ख़र्च (तय राशि वाले: 0, वे बजट से नहीं रुकते) */
    public static function campaignSpend(?int $campaignId = null): array
    {
        $rows = db()->all('SELECT c.id, c.pricing, c.rate, COALESCE(SUM(a.impressions), 0) imp, COALESCE(SUM(a.clicks), 0) clk FROM {p}ad_campaigns c LEFT JOIN {p}ads a ON a.campaign_id = c.id'
            . ($campaignId ? ' WHERE c.id = ' . $campaignId : '') . ' GROUP BY c.id');
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = match ($r['pricing']) {
                'cpm' => round($r['imp'] / 1000 * (float) $r['rate'], 2),
                'cpc' => round($r['clk'] * (float) $r['rate'], 2),
                default => 0.0,
            };
        }
        return $out;
    }

    /** स्लॉट के विज्ञापन (टार्गेटिंग, प्राथमिकता, बारी) */
    public static function pick(string $key): array
    {
        $ads = array_values(array_filter(self::live()[$key] ?? [], static function ($a) {
            foreach (['category_ids' => 'categories', 'location_ids' => 'locations'] as $col => $ctx) {
                $want = array_filter(array_map('intval', explode(',', (string) $a[$col])));
                if ($want && !array_intersect($want, self::$context[$ctx])) {
                    return false;
                }
            }
            return true;
        }));
        if (!$ads) {
            return [];
        }
        shuffle($ads); // बराबर प्राथमिकता में बारी-बारी
        usort($ads, static fn($x, $y) => (int) $y['priority'] <=> (int) $x['priority']);
        return array_slice($ads, 0, max(1, (int) $ads[0]['max_ads']));
    }

    /** स्लॉट का HTML ('' = कुछ नहीं) */
    public static function slot(string $key): string
    {
        if (!self::enabled() || !preg_match('/^[a-z0-9_-]{1,60}$/', $key)) {
            return '';
        }
        $ads = self::pick($key);
        if (!$ads) {
            return '';
        }
        $html = implode('', array_map([self::class, 'render'], $ads));
        return match ($key) {
            'popup' => '<div class="ad-popup" data-ad-popup data-hours="' . (int) setting('ads_popup_hours', '24') . '" data-delay="' . (int) setting('ads_popup_delay', '5') . '" role="dialog" aria-modal="true" aria-label="' . e(self::label()) . '" hidden>'
                . '<div class="ad-popup-box"><button type="button" class="ad-close" data-ad-close aria-label="बंद करें">×</button>' . $html . '</div></div>',
            'mobile_sticky' => '<aside class="ad-sticky" data-ad-sticky aria-label="विज्ञापन (नीचे)"><button type="button" class="ad-close" data-ad-close aria-label="बंद करें">×</button>' . $html . '</aside>',
            default => '<div class="ad-slot ad-slot-' . e($key) . '" data-ad-slot="' . e($key) . '">' . $html . '</div>',
        };
    }

    public static function label(): string
    {
        return (string) (setting('ads_label', 'विज्ञापन') ?: 'विज्ञापन');
    }

    public static function render(array $a): string
    {
        $cls = 'ad ad-' . $a['type'] . ($a['devices'] !== 'all' ? ' ad-only-' . $a['devices'] : '');
        $click = route('ad.click', ['id' => $a['id']]);
        $alt = e($a['text'] ?: $a['name']);
        $body = match ($a['type']) {
            'image' => $a['image'] ? '<a href="' . e($click) . '" target="_blank" rel="sponsored noopener">'
                . '<picture>' . ($a['mobile_image'] ? '<source media="(max-width: 760px)" srcset="' . e(upload_url($a['mobile_image'])) . '">' : '')
                . '<img src="' . e(upload_url($a['image'])) . '" alt="' . $alt . '" loading="lazy"></picture></a>' : '',
            'html', 'adsense' => (string) $a['code'], // सिर्फ़ ads.manage वाले सेव कर सकते हैं
            'video' => self::video($a, $click),
            'link' => '<a class="ad-textlink" href="' . e($click) . '" target="_blank" rel="sponsored noopener"><b>' . e($a['name']) . '</b>' . ($a['text'] ? '<span>' . e($a['text']) . '</span>' : '') . '<i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>',
            default => '',
        };
        if ($body === '') {
            return '';
        }
        return '<div class="' . e($cls) . '" data-ad-id="' . (int) $a['id'] . '"><span class="ad-label">' . e(self::label()) . '</span>' . $body . '</div>';
    }

    private static function video(array $a, string $click): string
    {
        $url = (string) $a['video_url'];
        if ($yt = EmbedService::youtubeEmbed($url)) {
            $v = '<div class="ad-video"><iframe src="' . e($yt) . '" title="' . e($a['name']) . '" loading="lazy" allow="encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
        } else {
            $src = str_starts_with($url, 'media/') ? upload_url($url) : EmbedService::streamUrl($url);
            if (!$src) {
                return '';
            }
            $v = '<video class="ad-video-file" src="' . e($src) . '" autoplay muted loop playsinline controls preload="metadata"></video>';
        }
        return $v . ($a['target_url'] ? '<a class="ad-more" href="' . e($click) . '" target="_blank" rel="sponsored noopener">' . e($a['text'] ?: 'और जानें') . ' <i class="fa-solid fa-arrow-right"></i></a>' : '');
    }

    /** ख़बर की सामग्री में N-वें पैराग्राफ़ के बाद "बीच का" विज्ञापन (आख़िर में नहीं) */
    public static function inject(string $html, string $key = 'article_inline'): string
    {
        $n = max(1, (int) setting('ads_inline_after', '3'));
        if (substr_count($html, '</p>') <= $n) {
            return $html;
        }
        $ad = self::slot($key);
        if ($ad === '') {
            return $html;
        }
        $pos = -1;
        for ($i = 0; $i < $n; $i++) {
            $pos = strpos($html, '</p>', $pos + 1);
        }
        return substr($html, 0, $pos + 4) . $ad . substr($html, $pos + 4);
    }

    /** सामग्री में [ad:स्लॉट] (पेज/ख़बर में कस्टम स्लॉट) */
    public static function shortcodes(string $html): string
    {
        if (!str_contains($html, '[ad:')) {
            return $html;
        }
        $html = preg_replace_callback('~<p>\s*\[ad:([a-z0-9_-]{1,60})\]\s*</p>~', static fn($m) => self::slot($m[1]), $html);
        return preg_replace_callback('~\[ad:([a-z0-9_-]{1,60})\]~', static fn($m) => self::slot($m[1]), $html);
    }

    // ---------- गिनती ----------

    /** दिखे हुए विज्ञापन; एक सत्र में एक विज्ञापन 30 सेकंड में एक बार */
    public static function recordImpressions(array $ids): int
    {
        if (NewsQuery::isBot()) {
            return 0;
        }
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids)))), 0, 20);
        $seen = (array) app('session')->get('ad_imp', []);
        $now = time();
        $ids = array_values(array_filter($ids, static fn($id) => ($seen[$id] ?? 0) < $now - 30));
        if (!$ids) {
            return 0;
        }
        $ids = array_map('intval', array_column(db()->all("SELECT id FROM {p}ads WHERE status = 'active' AND id IN (" . implode(',', $ids) . ')'), 'id'));
        if (!$ids) {
            return 0;
        }
        foreach ($ids as $id) {
            $seen[$id] = $now;
        }
        app('session')->set('ad_imp', array_slice($seen, -300, null, true));
        $in = implode(',', $ids);
        db()->query("UPDATE {p}ads SET impressions = impressions + 1 WHERE id IN ($in)");
        $day = date('Y-m-d');
        db()->query('INSERT INTO {p}ad_stats_daily (ad_id, day, impressions) VALUES ' . implode(',', array_map(static fn($id) => "($id, '$day', 1)", $ids))
            . ' ON DUPLICATE KEY UPDATE impressions = impressions + 1');
        return count($ids);
    }

    public static function recordClick(array $ad): void
    {
        if (NewsQuery::isBot()) {
            return;
        }
        $seen = (array) app('session')->get('ad_clk', []);
        if (($seen[$ad['id']] ?? 0) > time() - 10) {
            return;
        }
        $seen[$ad['id']] = time();
        app('session')->set('ad_clk', array_slice($seen, -100, null, true));
        db()->query('UPDATE {p}ads SET clicks = clicks + 1 WHERE id = ?', [$ad['id']]);
        db()->query('INSERT INTO {p}ad_stats_daily (ad_id, day, clicks) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE clicks = clicks + 1', [$ad['id'], date('Y-m-d')]);
    }

    public static function ctr(int $impressions, int $clicks): string
    {
        return $impressions ? number_format($clicks * 100 / $impressions, 2) . '%' : '—';
    }

    public static function changed(): void
    {
        self::$live = null;
        cache()->flush('ads');
        cache()->flush('home');
    }

    /** होमपेज बिल्डर के लिए चालू स्लॉट */
    public static function slotOptions(): array
    {
        return array_column(db()->all("SELECT slot_key, name FROM {p}ad_slots WHERE status = 'active' AND placement IN ('homepage', 'custom') ORDER BY is_system DESC, name"), 'name', 'slot_key');
    }
}
