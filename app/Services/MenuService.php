<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * मेनू: जगह (location) के हिसाब से नेस्टेड ट्री, हर आइटम का URL, कैश।
 * आइटम के प्रकार config/menus.php में; नए प्रकार (श्रेणी, लोकेशन…) उनकी टेबल बनते ही चालू।
 */
final class MenuService
{
    private const CACHE = 'menus.tree.';

    /** वेबसाइट के लिए: सिर्फ़ चालू आइटम, URL के साथ, नेस्टेड */
    public static function tree(string $location): array
    {
        return cache()->remember(self::CACHE . $location, 3600, static function () use ($location): array {
            $menu = db()->first('SELECT id, name FROM {p}menus WHERE location = ?', [$location]);
            if (!$menu) {
                return ['name' => '', 'items' => []];
            }
            $items = db()->all('SELECT * FROM {p}menu_items WHERE menu_id = ? AND is_active = 1 ORDER BY sort_order, id', [$menu['id']]);
            $refs = self::referenceRows($items);
            foreach ($items as &$it) {
                $it['href'] = self::url($it, $refs);
            }
            unset($it);
            $items = array_values(array_filter($items, static fn($i) => $i['href'] !== null));
            return ['name' => $menu['name'], 'items' => self::nest($items)];
        });
    }

    public static function clearCache(): void
    {
        cache()->flush('menus');
    }

    /** फ़्लैट सूची → parent_id से नेस्टेड */
    public static function nest(array $items, ?int $parent = null): array
    {
        $out = [];
        foreach ($items as $it) {
            if (($it['parent_id'] === null ? null : (int) $it['parent_id']) === $parent) {
                $it['children'] = self::nest($items, (int) $it['id']);
                $out[] = $it;
            }
        }
        return $out;
    }

    /** जिन प्रकारों की टेबल मौजूद है, वही उपलब्ध */
    public static function availableTypes(): array
    {
        $types = (array) config('menus.types', []);
        $existing = self::existingTables();
        return array_filter($types, static fn($t) => empty($t['table']) || in_array($t['table'], $existing, true));
    }

    public static function existingTables(): array
    {
        static $tables = null;
        if ($tables === null) {
            $prefix = db()->prefix();
            $tables = array_map(static fn($row) => substr((string) array_values((array) $row)[0], strlen($prefix)), db()->all('SHOW TABLES LIKE ' . db()->pdo()->quote(str_replace('_', '\\_', $prefix) . '%')));
        }
        return $tables;
    }

    /** पेज/श्रेणी आदि के नाम और slug एक साथ लाएँ (N+1 क्वेरी से बचाव) */
    public static function referenceRows(array $items): array
    {
        $types = (array) config('menus.types', []);
        $existing = self::existingTables();
        $out = [];
        $byType = [];
        foreach ($items as $it) {
            if (!empty($it['reference_id']) && !empty($types[$it['type']]['table'])) {
                $byType[$it['type']][] = (int) $it['reference_id'];
            }
        }
        foreach ($byType as $type => $ids) {
            $t = $types[$type];
            if (!in_array($t['table'], $existing, true)) {
                continue;
            }
            $extra = $t['table'] === 'pages' ? ", status, deleted_at" : '';
            foreach (db()->all("SELECT id, slug, `{$t['title_col']}` AS title$extra FROM {p}{$t['table']} WHERE id IN (" . Database::in($ids) . ')', $ids) as $r) {
                $out[$type][(int) $r['id']] = $r;
            }
        }
        return $out;
    }

    /** आइटम का URL; जुड़ा रिकॉर्ड हट गया या अप्रकाशित हो तो null (वेबसाइट पर नहीं दिखेगा) */
    public static function url(array $it, array $refs): ?string
    {
        $ref = $refs[$it['type']][(int) $it['reference_id']] ?? null;
        return match ($it['type']) {
            'home' => url(),
            'page' => ($ref && $ref['status'] === 'published' && $ref['deleted_at'] === null) ? url('page/' . $ref['slug']) : null,
            'category' => $ref ? url('category/' . $ref['slug']) : null,
            'location' => $ref ? url($ref['slug']) : null,
            'topic' => $ref ? url('topic/' . $ref['slug']) : null,
            // अपने मॉड्यूल (Phase 7-8) का पेज बनने तक: ई-पेपर छिपा, लाइव टीवी सेटिंग वाले लिंक पर
            'epaper' => app('router')->has('epaper') ? route('epaper') : null,
            'live_tv' => app('router')->has('live_tv') ? route('live_tv') : (setting('live_tv_url') ?: null),
            'custom' => url(ltrim((string) $it['url'], '/')),
            'external' => (string) $it['url'],
            default => null,
        };
    }

    /** URL सुरक्षित है? (javascript: आदि नहीं) */
    public static function safeUrl(string $url, bool $external): bool
    {
        if ($external) {
            return (bool) preg_match('~^(https?://[^\s<>"]+|mailto:[^\s<>"]+|tel:[+\d\s-]+)$~i', $url);
        }
        return (bool) preg_match('~^/?[^\s<>":]*$~', $url) && !str_starts_with($url, '//');
    }
}
