<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * ट्रेंडिंग इंजन (§49): सबसे ज़्यादा पढ़ी, सबसे ज़्यादा शेयर, ट्रेंडिंग (हाल के व्यू + शेयर, उम्र के साथ घटता स्कोर),
 * शहर/श्रेणी के हिसाब से लोकप्रिय। एडमिन: पिन (ऊपर) / छुपाएँ; ख़बर का "ट्रेंडिंग" फ़्लैग भी पिन माना जाता है।
 * एनालिटिक्स का डेटा न हो (नई साइट) तो ख़बर के कुल व्यू से।
 */
final class TrendingService
{
    private const TTL = 600;

    /** ['pin' => [ids], 'hide' => [ids]] — जिनकी अवधि बाकी है */
    public static function overrides(): array
    {
        return cache()->remember('trending.overrides', self::TTL, static function (): array {
            $o = ['pin' => [], 'hide' => []];
            try {
                foreach (db()->all('SELECT news_id, action FROM {p}trending_overrides WHERE until IS NULL OR until > NOW() ORDER BY created_at DESC') as $r) {
                    $o[$r['action']][] = (int) $r['news_id'];
                }
            } catch (\Throwable) {
                // टेबल अभी न हो (अपडेट बाकी)
            }
            return $o;
        });
    }

    /** ट्रेंडिंग स्कोर: [news_id => score] (सबसे ऊपर 200) */
    public static function scores(): array
    {
        return cache()->remember('trending.scores', self::TTL, static function (): array {
            $hours = max(3, min(168, (int) setting('trending_hours', '24')));
            $maxAge = max(1, min(60, (int) setting('trending_max_days', '7')));
            $s = [];
            try {
                // हाल के 3 घंटे के व्यू 3 गुना; एक विज़िटर के बार-बार व्यू नहीं (distinct)
                foreach (db()->all("SELECT content_id id, COUNT(DISTINCT visitor) u, COUNT(DISTINCT IF(created_at >= NOW() - INTERVAL 3 HOUR, visitor, NULL)) r
                    FROM {p}analytics_hits WHERE page_type = 'news' AND created_at >= NOW() - INTERVAL $hours HOUR GROUP BY content_id ORDER BY u DESC LIMIT 400") as $r) {
                    $s[(int) $r['id']] = (int) $r['u'] + 2 * (int) $r['r'];
                }
                foreach (db()->all('SELECT news_id id, SUM(shares) c FROM {p}analytics_shares WHERE day >= ? GROUP BY news_id', [date('Y-m-d', time() - $hours * 3600)]) as $r) {
                    $s[(int) $r['id']] = ($s[(int) $r['id']] ?? 0) + 5 * (int) $r['c'];
                }
            } catch (\Throwable) {
                return [];
            }
            if (!$s) {
                return [];
            }
            // उम्र के साथ स्कोर घटे: नई ख़बर को बढ़त; पुरानी (सीमा से ज़्यादा) बाहर
            $ids = array_keys($s);
            $out = [];
            foreach (db()->all('SELECT n.id, n.published_at FROM {p}news n WHERE n.id IN (' . Database::in($ids) . ') AND ' . NewsQuery::PUBLISHED . ' AND n.published_at >= NOW() - INTERVAL ' . $maxAge . ' DAY', $ids) as $n) {
                $age = max(0, (time() - strtotime((string) $n['published_at'])) / 3600);
                $out[(int) $n['id']] = round($s[(int) $n['id']] / pow($age + 2, 0.5), 3);
            }
            arsort($out);
            return array_slice($out, 0, 200, true);
        });
    }

    /** ट्रेंडिंग ख़बरें: पिन पहले, फिर स्कोर; कम हों तो पिछले 2 दिन की सबसे ज़्यादा पढ़ी */
    public static function trending(int $limit = 10, ?int $categoryId = null, ?int $locationId = null, array $exclude = []): array
    {
        $key = 'trending.list.' . md5(json_encode([$limit, $categoryId, $locationId, $exclude]));
        return cache()->remember($key, self::TTL, static function () use ($limit, $categoryId, $locationId, $exclude): array {
            $ov = self::overrides();
            $skip = array_merge($exclude, $ov['hide']);
            $pins = $ov['pin'];
            if (!$categoryId && !$locationId) {
                // ख़बर पर "ट्रेंडिंग" फ़्लैग = संपादक का पिन (पिछले 7 दिन की)
                $pins = array_merge($pins, array_map('intval', array_column(NewsQuery::list('n.is_trending = 1 AND n.published_at >= NOW() - INTERVAL 7 DAY', [], 20), 'id')));
            }
            $order = array_values(array_unique(array_merge($pins, array_keys(self::scores()))));
            $order = array_values(array_diff($order, $skip));
            $items = self::ordered($order, $limit, $categoryId, $locationId);
            if (count($items) < $limit) {
                $items = array_merge($items, self::mostRead(2, $limit - count($items), $categoryId, $locationId, array_merge($skip, array_column($items, 'id'))));
            }
            $pinSet = array_flip($pins);
            foreach ($items as &$n) {
                $n['pinned'] = isset($pinSet[(int) $n['id']]);
            }
            unset($n);
            return $items;
        });
    }

    /** सबसे ज़्यादा पढ़ी (पिछले N दिन के व्यू); डेटा कम हो तो ख़बर के कुल व्यू से */
    public static function mostRead(int $days = 7, int $limit = 5, ?int $categoryId = null, ?int $locationId = null, array $exclude = []): array
    {
        $days = max(1, min(90, $days));
        $key = 'trending.read.' . md5(json_encode([$days, $limit, $categoryId, $locationId, $exclude]));
        return cache()->remember($key, self::TTL, static function () use ($days, $limit, $categoryId, $locationId, $exclude): array {
            $skip = array_merge($exclude, self::overrides()['hide']);
            $ids = [];
            try {
                foreach (db()->all("SELECT dim_key id, SUM(views) v FROM {p}analytics_daily WHERE dim = 'news' AND day >= ? GROUP BY dim_key ORDER BY v DESC LIMIT 300", [date('Y-m-d', strtotime('-' . ($days - 1) . ' days'))]) as $r) {
                    $ids[] = (int) $r['id'];
                }
            } catch (\Throwable) {
            }
            $items = self::ordered(array_values(array_diff($ids, $skip)), $limit, $categoryId, $locationId);
            if (count($items) < $limit) {
                // नई साइट/कम डेटा: कुल व्यू वाली पुरानी गिनती से भरें
                [$w, $p] = self::filters($categoryId, $locationId, array_merge($skip, array_column($items, 'id')));
                $items = array_merge($items, NewsQuery::list("n.published_at >= NOW() - INTERVAL ? DAY AND $w", [$days, ...$p], $limit - count($items), 0, 'n.views DESC, n.published_at DESC'));
            }
            return $items;
        });
    }

    /** सबसे ज़्यादा शेयर (पिछले N दिन) */
    public static function mostShared(int $days = 7, int $limit = 10, ?int $categoryId = null, ?int $locationId = null): array
    {
        $days = max(1, min(90, $days));
        $key = 'trending.shared.' . md5(json_encode([$days, $limit, $categoryId, $locationId]));
        return cache()->remember($key, self::TTL, static function () use ($days, $limit, $categoryId, $locationId): array {
            $ids = [];
            $count = [];
            try {
                foreach (db()->all('SELECT news_id id, SUM(shares) c FROM {p}analytics_shares WHERE day >= ? GROUP BY news_id ORDER BY c DESC LIMIT 300', [date('Y-m-d', strtotime('-' . ($days - 1) . ' days'))]) as $r) {
                    $ids[] = (int) $r['id'];
                    $count[(int) $r['id']] = (int) $r['c'];
                }
            } catch (\Throwable) {
            }
            $items = self::ordered(array_values(array_diff($ids, self::overrides()['hide'])), $limit, $categoryId, $locationId);
            foreach ($items as &$n) {
                $n['share_count'] = $count[(int) $n['id']] ?? 0;
            }
            unset($n);
            return $items;
        });
    }

    /** ट्रेंडिंग पट्टी: फ़ीचर्ड टॉपिक + ट्रेंडिंग ख़बरों के टैग (स्कोर के हिसाब से); डेटा न हो तो सबसे ज़्यादा इस्तेमाल हुए टैग */
    public static function tags(int $limit = 10): array
    {
        return cache()->remember('trending.tags.' . $limit, self::TTL, static function () use ($limit): array {
            $topics = db()->all("SELECT name, slug, 'topic' AS kind FROM {p}topics WHERE status = 'active' AND is_featured = 1 ORDER BY sort_order, name LIMIT 5");
            $want = max(1, $limit - count($topics));
            $scores = array_slice(self::scores(), 0, 60, true);
            $tags = [];
            if ($scores) {
                $ids = array_keys($scores);
                $w = [];
                $meta = [];
                foreach (db()->all('SELECT nt.news_id, t.id, t.name, t.slug FROM {p}news_tags nt JOIN {p}tags t ON t.id = nt.tag_id WHERE nt.news_id IN (' . Database::in($ids) . ')', $ids) as $r) {
                    $w[(int) $r['id']] = ($w[(int) $r['id']] ?? 0) + $scores[(int) $r['news_id']];
                    $meta[(int) $r['id']] = ['name' => $r['name'], 'slug' => $r['slug'], 'kind' => 'tag'];
                }
                arsort($w);
                foreach (array_slice(array_keys($w), 0, $want) as $id) {
                    $tags[] = $meta[$id];
                }
            }
            if (count($tags) < $want) {
                $have = array_column($tags, 'slug');
                foreach (db()->all("SELECT t.name, t.slug, 'tag' AS kind, COUNT(*) c FROM {p}news_tags nt JOIN {p}tags t ON t.id = nt.tag_id JOIN {p}news n ON n.id = nt.news_id
                    WHERE " . NewsQuery::PUBLISHED . ' AND n.published_at >= NOW() - INTERVAL 7 DAY GROUP BY t.id ORDER BY c DESC LIMIT ' . ($want + count($have))) as $t) {
                    if (count($tags) < $want && !in_array($t['slug'], $have, true)) {
                        unset($t['c']);
                        $tags[] = $t;
                    }
                }
            }
            return array_merge($topics, $tags);
        });
    }

    /** दिए क्रम में प्रकाशित ख़बरें (श्रेणी/लोकेशन फ़िल्टर के साथ) */
    private static function ordered(array $ids, int $limit, ?int $categoryId, ?int $locationId): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids)))), 0, 300);
        if (!$ids || $limit < 1) {
            return [];
        }
        [$w, $p] = self::filters($categoryId, $locationId, []);
        $rows = NewsQuery::list('n.id IN (' . Database::in($ids) . ") AND $w", [...$ids, ...$p], 100);
        $pos = array_flip($ids);
        usort($rows, static fn($a, $b) => $pos[(int) $a['id']] <=> $pos[(int) $b['id']]);
        return array_slice($rows, 0, $limit);
    }

    private static function filters(?int $categoryId, ?int $locationId, array $exclude): array
    {
        $w = ['1=1'];
        $p = [];
        if ($categoryId) {
            [$cw, $cp] = NewsQuery::categoryWhere($categoryId);
            $w[] = $cw;
            $p = array_merge($p, $cp);
        }
        if ($locationId) {
            [$lw, $lp] = NewsQuery::locationWhere($locationId);
            $w[] = $lw;
            $p = array_merge($p, $lp);
        }
        $exclude = array_values(array_filter(array_map('intval', $exclude)));
        if ($exclude) {
            $w[] = 'n.id NOT IN (' . Database::in($exclude) . ')';
            $p = array_merge($p, $exclude);
        }
        return [implode(' AND ', $w), $p];
    }

    /** पिन/छुपाएँ बदलने पर */
    public static function flush(): void
    {
        cache()->flush('trending');
        cache()->flush('home');
    }
}
