<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Paginator;

/**
 * वेबसाइट के लिए ख़बरें पढ़ना। हर क्वेरी में सिर्फ़ प्रकाशित, समय आ चुकी, ट्रैश से बाहर की ख़बरें।
 * कार्ड के लिए ज़रूरी खाने + श्रेणी/लोकेशन का नाम एक ही क्वेरी में (N+1 नहीं)।
 */
final class NewsQuery
{
    public const PUBLISHED = "n.status = 'published' AND n.deleted_at IS NULL AND n.published_at <= NOW()";
    private const COLS = 'n.id, n.title, n.subtitle, n.slug, n.summary, n.featured_image, n.image_caption, n.published_at, n.video_url, n.views, n.word_count,
        n.is_breaking, n.is_live, n.is_exclusive, n.is_sponsored, n.category_id, n.location_id,
        c.name AS category, c.slug AS category_slug, c.color AS category_color, l.name AS location';
    private const FROM = '{p}news n LEFT JOIN {p}categories c ON c.id = n.category_id LEFT JOIN {p}locations l ON l.id = n.location_id';

    /** @param string $where अतिरिक्त शर्त (n. alias के साथ) */
    public static function list(string $where = '1=1', array $params = [], int $limit = 10, int $offset = 0, string $order = 'n.published_at DESC, n.id DESC'): array
    {
        $limit = max(1, min($limit, 100));
        return db()->all('SELECT ' . self::COLS . ' FROM ' . self::FROM . ' WHERE ' . self::PUBLISHED . " AND ($where) ORDER BY $order LIMIT $limit OFFSET " . max(0, $offset), $params);
    }

    public static function count(string $where = '1=1', array $params = []): int
    {
        return (int) db()->value('SELECT COUNT(*) FROM {p}news n WHERE ' . self::PUBLISHED . " AND ($where)", $params);
    }

    public static function page(string $where, array $params, int $page, ?int $perPage = null, int $maxPages = 500): Paginator
    {
        $perPage ??= max(5, min(60, (int) setting('posts_per_page', 12)));
        $page = max(1, min($page, $maxPages));
        $total = min(self::count($where, $params), $perPage * $maxPages);
        return new Paginator(self::list($where, $params, $perPage, Paginator::offset($page, $perPage)), $total, $perPage, $page);
    }

    public static function latest(int $limit = 10, array $exclude = []): array
    {
        [$w, $p] = self::exclude($exclude);
        return self::list($w, $p, $limit);
    }

    private static function exclude(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        return $ids ? ['n.id NOT IN (' . Database::in($ids) . ')', $ids] : ['1=1', []];
    }

    /** श्रेणी + उसकी उप-श्रेणियाँ */
    public static function categoryWhere(int $categoryId): array
    {
        return ['(n.category_id = ? OR n.category_id IN (SELECT id FROM {p}categories WHERE parent_id = ?))', [$categoryId, $categoryId]];
    }

    /** लोकेशन + नीचे की सभी (देश/मंडल जैसे बिना path वाले स्तर के लिए भी) */
    public static function locationWhere(int $locationId): array
    {
        $ids = self::descendants($locationId);
        return ['n.location_id IN (' . Database::in($ids) . ')', $ids];
    }

    public static function descendants(int $id): array
    {
        return cache()->remember('locations.tree.' . $id, 600, static function () use ($id): array {
            $all = [$id];
            $level = [$id];
            for ($d = 0; $d < 8 && $level; $d++) {
                $level = array_map('intval', array_column(db()->all('SELECT id FROM {p}locations WHERE parent_id IN (' . Database::in($level) . ')', $level), 'id'));
                $all = array_merge($all, $level);
            }
            return array_values(array_unique($all));
        });
    }

    public static function topicWhere(int $topicId): array
    {
        return ['n.id IN (SELECT news_id FROM {p}news_topics WHERE topic_id = ?)', [$topicId]];
    }

    public static function tagWhere(int $tagId): array
    {
        return ['n.id IN (SELECT news_id FROM {p}news_tags WHERE tag_id = ?)', [$tagId]];
    }

    /** खोज: शीर्षक, सार, उप-शीर्षक और टैग में (हर शब्द ज़रूरी) */
    public static function searchWhere(string $q, int $categoryId = 0): array
    {
        $words = array_slice(array_values(array_filter(preg_split('/\s+/u', trim($q)), static fn($w) => mb_strlen($w) >= 2)), 0, 6);
        $parts = [];
        $params = [];
        foreach ($words as $w) {
            $like = '%' . addcslashes($w, '%_\\') . '%';
            $parts[] = '(n.title LIKE ? OR n.summary LIKE ? OR n.subtitle LIKE ? OR n.id IN (SELECT nt.news_id FROM {p}news_tags nt JOIN {p}tags t ON t.id = nt.tag_id WHERE t.name LIKE ?))';
            array_push($params, $like, $like, $like, $like);
        }
        if (!$parts) {
            return ['1=0', []];
        }
        if ($categoryId > 0) {
            [$cw, $cp] = self::categoryWhere($categoryId);
            $parts[] = $cw;
            array_push($params, ...$cp);
        }
        return [implode(' AND ', $parts), $params];
    }

    /** फ़्लैग वाली (is_featured, is_editor_pick, is_trending…) */
    public static function flagged(string $flag, int $limit, array $exclude = []): array
    {
        if (!isset(\App\Models\News::FLAGS[$flag])) {
            return [];
        }
        [$w, $p] = self::exclude($exclude);
        return self::list("n.`$flag` = 1 AND $w", $p, $limit);
    }

    /** ID के क्रम में (होमपेज की हाथ से चुनी ख़बरें) */
    public static function byIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $rows = array_column(self::list('n.id IN (' . Database::in($ids) . ')', $ids, count($ids)), null, 'id');
        return array_values(array_filter(array_map(static fn($id) => $rows[$id] ?? null, $ids)));
    }

    /** सबसे ज़्यादा पढ़ी (पिछले X दिन में प्रकाशित ख़बरों के व्यूज़ से) */
    public static function mostRead(int $days = 7, int $limit = 5): array
    {
        return self::list('n.published_at >= NOW() - INTERVAL ? DAY', [max(1, $days)], $limit, 0, 'n.views DESC, n.published_at DESC');
    }

    /** संबंधित: हाथ से चुनी पहले, फिर उसी श्रेणी/टैग की */
    public static function related(array $news, int $limit = 6): array
    {
        $manual = self::list('n.id IN (SELECT related_id FROM {p}news_related WHERE news_id = ?)', [$news['id']], $limit);
        $have = [$news['id'], ...array_column($manual, 'id')];
        if (count($manual) < $limit) {
            [$w, $p] = self::exclude($have);
            $more = self::list("$w AND (n.id IN (SELECT nt2.news_id FROM {p}news_tags nt2 WHERE nt2.tag_id IN (SELECT tag_id FROM {p}news_tags WHERE news_id = ?))" .
                ($news['category_id'] ? ' OR n.category_id = ?' : '') . ')', [...$p, $news['id'], ...($news['category_id'] ? [(int) $news['category_id']] : [])], $limit - count($manual));
            $manual = array_merge($manual, $more);
        }
        return $manual;
    }

    /** पट्टी: पिछले 48 घंटे की ब्रेकिंग, न हों तो ताज़ा */
    public static function ticker(int $limit = 10): array
    {
        return cache()->remember('home.ticker', 120, static function () use ($limit): array {
            $rows = self::list('n.is_breaking = 1 AND n.published_at >= NOW() - INTERVAL 48 HOUR', [], $limit);
            return ['breaking' => (bool) $rows, 'items' => $rows ?: self::latest($limit)];
        });
    }

    /** ट्रेंडिंग पट्टी: फ़ीचर्ड टॉपिक + पिछले 7 दिन के सबसे ज़्यादा इस्तेमाल हुए टैग */
    public static function trending(int $limit = 10): array
    {
        return cache()->remember('home.trending', 600, static function () use ($limit): array {
            $topics = db()->all("SELECT name, slug, 'topic' AS kind FROM {p}topics WHERE status = 'active' AND is_featured = 1 ORDER BY sort_order, name LIMIT 5");
            $tags = db()->all(
                "SELECT t.name, t.slug, 'tag' AS kind, COUNT(*) c FROM {p}news_tags nt JOIN {p}tags t ON t.id = nt.tag_id JOIN {p}news n ON n.id = nt.news_id
                 WHERE " . self::PUBLISHED . ' AND n.published_at >= NOW() - INTERVAL 7 DAY GROUP BY t.id ORDER BY c DESC LIMIT ' . max(1, $limit - count($topics))
            );
            return array_merge($topics, $tags);
        });
    }

    /** एक सत्र में एक ख़बर का एक व्यू; बॉट नहीं */
    public static function countView(array $news): void
    {
        $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($ua === '' || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|preview|curl|wget|python|headless/', $ua)) {
            return;
        }
        $seen = (array) app('session')->get('viewed_news', []);
        if (in_array((int) $news['id'], $seen, true)) {
            return;
        }
        $seen[] = (int) $news['id'];
        app('session')->set('viewed_news', array_slice($seen, -200));
        db()->query('UPDATE {p}news SET views = views + 1 WHERE id = ?', [$news['id']]);
    }
}
