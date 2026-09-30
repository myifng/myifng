<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Reader;

/** पाठक: सेव, फ़ॉलो, इतिहास, पसंद, "आपके लिए" */
final class ReaderService
{
    public const FOLLOW_TYPES = ['category' => 'श्रेणी', 'reporter' => 'रिपोर्टर', 'location' => 'शहर / लोकेशन', 'topic' => 'टॉपिक'];

    /** सूचना पसंद: [कुंजी => [लेबल, डिफ़ॉल्ट]] */
    public const PREFS = [
        'breaking' => ['ब्रेकिंग न्यूज़', 1],
        'followed' => ['फ़ॉलो की गई श्रेणी/रिपोर्टर/शहर की नई ख़बर (खाते में)', 1],
        'epaper' => ['आज का ई-पेपर', 0],
        'comments' => ['मेरी टिप्पणी का जवाब / स्वीकृति', 1],
        'email' => ['ज़रूरी सूचनाएँ ईमेल पर भी', 0],
    ];

    public static function prefs(array $reader): array
    {
        $saved = json_decode((string) ($reader['prefs'] ?? ''), true);
        $out = [];
        foreach (self::PREFS as $k => [, $def]) {
            $out[$k] = (int) (is_array($saved) && array_key_exists($k, $saved) ? (bool) $saved[$k] : $def);
        }
        return $out;
    }

    public static function isBookmarked(int $readerId, int $newsId): bool
    {
        return (bool) db()->value('SELECT 1 FROM {p}reader_bookmarks WHERE reader_id = ? AND news_id = ?', [$readerId, $newsId]);
    }

    /** सेव/हटाएँ; नया हाल लौटाए */
    public static function toggleBookmark(int $readerId, int $newsId): bool
    {
        if (self::isBookmarked($readerId, $newsId)) {
            db()->query('DELETE FROM {p}reader_bookmarks WHERE reader_id = ? AND news_id = ?', [$readerId, $newsId]);
            return false;
        }
        db()->query('INSERT IGNORE INTO {p}reader_bookmarks (reader_id, news_id) VALUES (?, ?)', [$readerId, $newsId]);
        return true;
    }

    public static function follows(int $readerId): array
    {
        $out = array_fill_keys(array_keys(self::FOLLOW_TYPES), []);
        foreach (db()->all('SELECT type, target_id FROM {p}reader_follows WHERE reader_id = ?', [$readerId]) as $f) {
            $out[$f['type']][] = (int) $f['target_id'];
        }
        return $out;
    }

    public static function isFollowing(int $readerId, string $type, int $id): bool
    {
        return (bool) db()->value('SELECT 1 FROM {p}reader_follows WHERE reader_id = ? AND type = ? AND target_id = ?', [$readerId, $type, $id]);
    }

    /** फ़ॉलो का लक्ष्य मौजूद है? [नाम, url] */
    public static function target(string $type, int $id): ?array
    {
        return match ($type) {
            'category' => ($r = db()->first("SELECT name, slug FROM {p}categories WHERE id = ? AND status = 'active'", [$id])) ? [$r['name'], route('category', ['slug' => $r['slug']])] : null,
            'topic' => ($r = db()->first("SELECT name, slug FROM {p}topics WHERE id = ? AND status = 'active'", [$id])) ? [$r['name'], route('topic', ['slug' => $r['slug']])] : null,
            'location' => ($r = db()->first("SELECT name, path FROM {p}locations WHERE id = ? AND status = 'active'", [$id])) ? [$r['name'], $r['path'] ? url($r['path']) : null] : null,
            'reporter' => ($r = db()->first("SELECT u.name FROM {p}users u WHERE u.id = ? AND u.deleted_at IS NULL AND EXISTS (SELECT 1 FROM {p}news n WHERE n.reporter_id = u.id AND n.status = 'published')", [$id])) ? [$r['name'], null] : null,
            default => null,
        };
    }

    public static function toggleFollow(int $readerId, string $type, int $id): bool
    {
        if (self::isFollowing($readerId, $type, $id)) {
            db()->query('DELETE FROM {p}reader_follows WHERE reader_id = ? AND type = ? AND target_id = ?', [$readerId, $type, $id]);
            return false;
        }
        if ((int) db()->value('SELECT COUNT(*) FROM {p}reader_follows WHERE reader_id = ?', [$readerId]) >= 100) {
            return false;
        }
        db()->query('INSERT IGNORE INTO {p}reader_follows (reader_id, type, target_id) VALUES (?, ?, ?)', [$readerId, $type, $id]);
        return true;
    }

    /** पढ़ी गई ख़बर (इतिहास चालू हो तो; 500 तक) */
    public static function logRead(array $reader, int $newsId): void
    {
        if (!(int) $reader['history_enabled']) {
            return;
        }
        db()->query('INSERT INTO {p}reader_history (reader_id, news_id, read_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE read_at = NOW()', [$reader['id'], $newsId]);
        if (random_int(1, 50) === 1) {
            db()->query('DELETE FROM {p}reader_history WHERE reader_id = ? AND read_at < (SELECT t.r FROM (SELECT read_at r FROM {p}reader_history WHERE reader_id = ? ORDER BY read_at DESC LIMIT 1 OFFSET 500) t)', [$reader['id'], $reader['id']]);
        }
    }

    /** फ़ॉलो की गई चीज़ों की ख़बरों की शर्त (श्रेणी/लोकेशन में नीचे वाली भी) */
    public static function feedWhere(int $readerId): ?array
    {
        $f = self::follows($readerId);
        $or = [];
        $p = [];
        foreach ($f['category'] as $id) {
            [$w, $pp] = NewsQuery::categoryWhere($id);
            $or[] = $w;
            array_push($p, ...$pp);
        }
        foreach ($f['location'] as $id) {
            [$w, $pp] = NewsQuery::locationWhere($id);
            $or[] = $w;
            array_push($p, ...$pp);
        }
        foreach ($f['topic'] as $id) {
            [$w, $pp] = NewsQuery::topicWhere($id);
            $or[] = $w;
            array_push($p, ...$pp);
        }
        if ($f['reporter']) {
            $or[] = 'n.reporter_id IN (' . implode(',', array_map('intval', $f['reporter'])) . ')';
        }
        return $or ? ['(' . implode(' OR ', $or) . ')', $p] : null;
    }

    /** पाठक का शहर: खाते से, वरना "मेरा शहर" कुकी से */
    public static function cityId(?array $reader): ?int
    {
        if ($reader && $reader['location_id']) {
            return (int) $reader['location_id'];
        }
        $c = LocationService::myCity();
        return $c ? (int) $c['id'] : null;
    }

    /** खाता हटाना: टिप्पणियाँ गुमनाम, सब्सक्रिप्शन अलग, बाकी सब हटे */
    public static function erase(int $readerId): void
    {
        db()->query("UPDATE {p}comments SET name = 'पूर्व पाठक', email = NULL, reader_id = NULL WHERE reader_id = ?", [$readerId]);
        Reader::delete($readerId);
    }
}
