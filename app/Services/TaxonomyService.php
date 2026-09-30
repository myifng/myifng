<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Str;

/**
 * श्रेणी, टॉपिक, टैग, लोकेशन की साझा बातें: यूनिक स्लग, इस्तेमाल की जाँच (हटाने से पहले), आरक्षित शब्द।
 * ख़बरों की टेबल Phase 4 में बनेगी; तब तक "इस्तेमाल" की गिनती 0 रहती है और कोड बदले बिना अपने आप चालू हो जाती है।
 */
final class TaxonomyService
{
    /** ये शब्द वेबसाइट के अपने रास्ते हैं; राज्य का स्लग इनमें से नहीं हो सकता (URL: /uttar-pradesh/) */
    public const RESERVED = ['admin', 'api', 'page', 'category', 'topic', 'tag', 'tags', 'search', 'author', 'reporter', 'epaper', 'e-paper',
        'live-tv', 'live', 'video', 'videos', 'photo', 'photos', 'gallery', 'web-stories', 'podcast', 'sitemap', 'sitemap-xml', 'feed', 'rss',
        'amp', 'news', 'login', 'logout', 'register', 'install', 'public', 'storage', 'uploads', 'assets', 'my-city', 'newsletter', 'contact', 'latest', 'trending',
        'audio', 'ad', 'ads', 'live-updates', 'join-as-reporter', 'application-status', 'verify-reporter', 'account', 'poll', 'polls', 'push', 'comments', 'form', 'send-news', 'complaint', 'careers'];

    /** ख़बरों में इस्तेमाल: [टेबल, कॉलम] (Phase 4 की टेबल; न हों तो जाँच छोड़ दी जाती है) */
    private const USAGE = [
        'categories' => [['news', 'category_id'], ['news_categories', 'category_id']],
        'topics' => [['news_topics', 'topic_id']],
        'tags' => [['news_tags', 'tag_id']],
        'locations' => [['news', 'location_id'], ['news_locations', 'location_id']],
    ];

    /** स्लग: दिया गया या नाम से अंग्रेज़ी में; दोहराव पर -2, -3 */
    public static function slug(string $table, string $given, string $name, int $exceptId = 0, string $fallback = 'item'): string
    {
        $slug = Str::slug($given !== '' ? $given : $name);
        return self::unique($table, $slug !== '' ? $slug : $fallback, $exceptId);
    }

    public static function unique(string $table, string $slug, int $exceptId = 0, string $column = 'slug'): string
    {
        $base = $slug;
        $i = 2;
        while ((int) db()->value("SELECT COUNT(*) FROM {p}$table WHERE `$column` = ? AND id <> ?", [$slug, $exceptId]) > 0) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    /** कितनी ख़बरों में इस्तेमाल हो रहा है */
    public static function usage(string $table, int $id): int
    {
        $existing = MenuService::existingTables();
        $n = 0;
        foreach (self::USAGE[$table] ?? [] as [$t, $col]) {
            if (in_array($t, $existing, true) && self::hasColumn($t, $col)) {
                $n += (int) db()->value("SELECT COUNT(*) FROM {p}$t WHERE `$col` = ?", [$id]);
            }
        }
        return $n;
    }

    private static function hasColumn(string $table, string $col): bool
    {
        return db()->first('SHOW COLUMNS FROM {p}' . $table . ' LIKE ' . db()->pdo()->quote($col)) !== null;
    }

    /** हटाने पर मेनू से उसके लिंक भी हटें (वरना मेनू बिल्डर में "टूटा लिंक") */
    public static function removeMenuLinks(string $type, int $id): void
    {
        db()->query('DELETE FROM {p}menu_items WHERE type = ? AND reference_id = ?', [$type, $id]);
        MenuService::clearCache();
    }

    /** एक ही parent के भाई-बहनों में ऊपर/नीचे (sort_order की अदला-बदली) */
    public static function move(string $table, int $id, string $dir, ?int $parentId): bool
    {
        $where = $parentId === null ? 'parent_id IS NULL' : 'parent_id = ?';
        $params = $parentId === null ? [] : [$parentId];
        $ids = array_map('intval', array_column(db()->all("SELECT id FROM {p}$table WHERE $where ORDER BY sort_order, id", $params), 'id'));
        $i = array_search($id, $ids, true);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($i === false || !isset($ids[$j])) {
            return false;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        db()->transaction(static function ($db) use ($ids, $table) {
            foreach ($ids as $k => $rid) {
                $db->query("UPDATE {p}$table SET sort_order = ? WHERE id = ?", [$k + 1, $rid]);
            }
        });
        return true;
    }

    /** Font Awesome आइकन की class सुरक्षित है? (fa-solid fa-futbol या fa-futbol) */
    public static function validIcon(?string $icon): bool
    {
        return $icon === null || $icon === '' || (bool) preg_match('/^(fa-(solid|regular|brands)\s+)?fa-[a-z0-9-]{1,40}$/', $icon);
    }
}
