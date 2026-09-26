<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Paginator;
use App\Core\Request;
use App\Core\ValidationException;

/**
 * वीडियो, फ़ोटो गैलरी, वेब स्टोरी, ऑडियो: साझा क्वेरी, URL, प्रकाशन के नियम और फ़ॉर्म के साझा खाने।
 * सार्वजनिक: status = published और published_at बीत चुका (आगे की तारीख़ = शेड्यूल)।
 */
final class MultimediaService
{
    public const KINDS = [
        'video'   => ['table' => 'videos', 'route' => 'video.show', 'label' => 'वीडियो', 'module' => 'videos', 'icon' => 'fa-play'],
        'gallery' => ['table' => 'galleries', 'route' => 'gallery.show', 'label' => 'फ़ोटो गैलरी', 'module' => 'galleries', 'icon' => 'fa-images'],
        'story'   => ['table' => 'web_stories', 'route' => 'story.show', 'label' => 'वेब स्टोरी', 'module' => 'web_stories', 'icon' => 'fa-mobile-screen'],
        'audio'   => ['table' => 'audio_items', 'route' => 'audio.show', 'label' => 'ऑडियो', 'module' => 'audio', 'icon' => 'fa-headphones'],
    ];

    public const STATUSES = ['draft' => 'ड्राफ़्ट', 'published' => 'प्रकाशित'];

    public static function published(string $a = 'x'): string
    {
        return "$a.status = 'published' AND $a.published_at IS NOT NULL AND $a.published_at <= NOW()";
    }

    private static function select(string $kind): string
    {
        $t = self::KINDS[$kind]['table'];
        $extra = match ($kind) {
            'gallery' => ', (SELECT COUNT(*) FROM {p}gallery_photos gp WHERE gp.gallery_id = x.id) AS photo_count',
            'story' => ', (SELECT COUNT(*) FROM {p}web_story_slides ws WHERE ws.story_id = x.id) AS slide_count',
            'audio' => ', s.title AS series, s.slug AS series_slug, s.cover AS series_cover',
            'video' => ', pl.name AS playlist, pl.slug AS playlist_slug',
            default => '',
        };
        $join = match ($kind) {
            'audio' => ' LEFT JOIN {p}podcast_series s ON s.id = x.series_id',
            'video' => ' LEFT JOIN {p}video_playlists pl ON pl.id = x.playlist_id',
            default => '',
        };
        return "SELECT x.*, c.name AS category, c.slug AS category_slug$extra FROM {p}$t x LEFT JOIN {p}categories c ON c.id = x.category_id$join";
    }

    public static function list(string $kind, string $where = '1=1', array $params = [], int $limit = 12, int $offset = 0, string $order = 'x.published_at DESC, x.id DESC'): array
    {
        $limit = max(1, min($limit, 100));
        return array_map(static fn($r) => $r + ['kind' => $kind],
            db()->all(self::select($kind) . ' WHERE ' . self::published() . " AND ($where) ORDER BY $order LIMIT $limit OFFSET " . max(0, $offset), $params));
    }

    public static function count(string $kind, string $where = '1=1', array $params = []): int
    {
        return (int) db()->value('SELECT COUNT(*) FROM {p}' . self::KINDS[$kind]['table'] . ' x WHERE ' . self::published() . " AND ($where)", $params);
    }

    public static function page(string $kind, string $where, array $params, int $page, int $per = 12): Paginator
    {
        $page = max(1, min($page, 500));
        return new Paginator(self::list($kind, $where, $params, $per, Paginator::offset($page, $per)), self::count($kind, $where, $params), $per, $page);
    }

    public static function find(string $kind, string $slug): ?array
    {
        $r = db()->first(self::select($kind) . ' WHERE x.slug = ? AND ' . self::published(), [$slug]);
        return $r ? $r + ['kind' => $kind] : null;
    }

    public static function url(string $kind, array $row): string
    {
        return route(self::KINDS[$kind]['route'], ['slug' => $row['slug']]);
    }

    /** होमपेज/सूची बदलने पर */
    public static function changed(): void
    {
        cache()->flush('home');
    }

    /** प्रशासन सूची: स्थिति टैब (ड्राफ़्ट/प्रकाशित/शेड्यूल), खोज, प्रकार */
    public static function adminList(string $table, Request $request, array $types = [], int $per = 25): array
    {
        $where = ['1=1'];
        $params = [];
        $status = $request->str('status');
        if ($status === 'draft') {
            $where[] = "x.status = 'draft'";
        } elseif ($status === 'published') {
            $where[] = "x.status = 'published' AND x.published_at <= NOW()";
        } elseif ($status === 'scheduled') {
            $where[] = "x.status = 'published' AND x.published_at > NOW()";
        } else {
            $status = '';
        }
        $type = $request->str('type');
        if ($types && isset($types[$type])) {
            $where[] = 'x.type = ?';
            $params[] = $type;
        } else {
            $type = '';
        }
        if (($q = $request->str('q')) !== '') {
            $where[] = '(x.title LIKE ? OR x.slug LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like);
        }
        $w = implode(' AND ', $where);
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}$table x WHERE $w", $params);
        $items = db()->all("SELECT x.*, c.name AS category, u.name AS author FROM {p}$table x LEFT JOIN {p}categories c ON c.id = x.category_id LEFT JOIN {p}users u ON u.id = x.created_by
                            WHERE $w ORDER BY x.updated_at DESC, x.id DESC LIMIT $per OFFSET " . Paginator::offset($page, $per), $params);
        $c = db()->first("SELECT COUNT(*) a, SUM(status = 'draft') d, SUM(status = 'published' AND published_at <= NOW()) p, SUM(status = 'published' AND published_at > NOW()) s FROM {p}$table");
        return [
            'items' => new Paginator($items, $total, $per, $page), 'status' => $status, 'type' => $type, 'q' => $q,
            'counts' => ['' => (int) $c['a'], 'published' => (int) $c['p'], 'scheduled' => (int) $c['s'], 'draft' => (int) $c['d']],
        ];
    }

    /** प्रशासन में दिखने वाली स्थिति: ड्राफ़्ट / शेड्यूल / प्रकाशित */
    public static function state(array $row): string
    {
        if ($row['status'] !== 'published') {
            return 'draft';
        }
        return $row['published_at'] && strtotime($row['published_at']) > time() ? 'scheduled' : 'published';
    }

    /**
     * साझा खाने: शीर्षक, स्लग, विवरण, कवर, श्रेणी, स्थिति, प्रकाशन समय, फ़ीचर्ड, SEO
     * बिना "publish" अनुमति: नया = ड्राफ़्ट; पुराना = जैसी स्थिति थी वैसी (प्रकाशित को भी ड्राफ़्ट नहीं कर सकता)
     */
    public static function payload(Request $request, string $table, string $module, ?array $row, array $extraRules = [], array $labels = []): array
    {
        $val = \App\Core\Validator::make($request->all(), [
            'title' => 'required|max:255', 'slug' => 'nullable|slug|max:190', 'description' => 'nullable|max:5000',
            'category_id' => 'nullable|integer|exists:categories,id', 'status' => 'required|in:draft,published', 'published_at' => 'nullable|date',
            'meta_title' => 'nullable|max:190', 'meta_description' => 'nullable|max:320',
        ] + $extraRules, $labels + [
            'title' => 'शीर्षक', 'slug' => 'URL (स्लग)', 'description' => 'विवरण', 'category_id' => 'श्रेणी', 'status' => 'स्थिति', 'published_at' => 'प्रकाशन का समय',
            'meta_title' => 'SEO शीर्षक', 'meta_description' => 'SEO विवरण',
        ]);
        if ($val->fails()) {
            throw new ValidationException($val->errors(), $request->post());
        }
        $v = $val->validated();
        $title = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $v['title'])));
        if ($title === '') {
            throw new ValidationException(['title' => 'शीर्षक में सादा टेक्स्ट लिखें।'], $request->post());
        }
        $cover = $request->str('cover');
        if ($cover !== ($row['cover'] ?? '') && !MediaService::validImagePath($cover)) {
            throw new ValidationException(['cover' => 'इमेज मीडिया लाइब्रेरी से चुनें।'], $request->post());
        }
        $canPublish = can($module . '.publish');
        $status = $canPublish ? $v['status'] : ($row['status'] ?? 'draft');
        $at = $v['published_at'] ? date('Y-m-d H:i:s', strtotime((string) $v['published_at'])) : null;
        if ($status === 'published') {
            $at = $canPublish ? ($at ?? ($row['published_at'] ?? now())) : ($row['published_at'] ?? now());
        } else {
            $at = $canPublish ? $at : ($row['published_at'] ?? null);
        }
        return [
            'title' => $title, 'slug' => TaxonomyService::slug($table, (string) $v['slug'], $title, (int) ($row['id'] ?? 0), 'item'),
            'description' => $v['description'] !== null ? trim(strip_tags((string) $v['description'])) : null,
            'cover' => $cover ?: null, 'category_id' => $v['category_id'] ? (int) $v['category_id'] : null,
            'status' => $status, 'published_at' => $at, 'is_featured' => $request->bool('is_featured') ? 1 : 0,
            'meta_title' => $v['meta_title'] ?: null, 'meta_description' => $v['meta_description'] ?: null,
            'updated_by' => auth()->id(),
        ] + array_intersect_key($v, $extraRules);
    }
}
