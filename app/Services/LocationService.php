<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Str;
use App\Models\Location;

/**
 * लोकेशन इंजन: URL वाला path, पेड़ (tree), चक्र की जाँच, खोज, CSV इम्पोर्ट/एक्सपोर्ट, "मेरा शहर"।
 * path = ऊपर के URL वाले स्तरों के स्लग + अपना स्लग (देश और मंडल छोड़कर): uttar-pradesh/maharajganj
 */
final class LocationService
{
    /** वेबसाइट पर पाठक जिन स्तरों को "मेरा शहर" चुन सकता है */
    public const MY_CITY_TYPES = ['state', 'district', 'city', 'tehsil'];
    public const IMPORT_MAX_ROWS = 5000;
    public const IMPORT_MAX_BYTES = 2 * 1024 * 1024;

    /** parent के नीचे इस स्लग का path (देश/मंडल का null) */
    public static function pathFor(?int $parentId, string $type, string $slug): ?string
    {
        if (in_array($type, Location::NO_URL, true)) {
            return null;
        }
        $pid = $parentId;
        $seen = [];
        while ($pid !== null && !isset($seen[$pid])) {
            $seen[$pid] = true;
            $p = db()->first('SELECT parent_id, path FROM {p}locations WHERE id = ?', [$pid]);
            if (!$p) {
                break;
            }
            if ($p['path'] !== null) {
                return $p['path'] . '/' . $slug;
            }
            $pid = $p['parent_id'] === null ? null : (int) $p['parent_id'];
        }
        return $slug;
    }

    /** $id को $newParent के नीचे रखने से चक्र बनेगा? (ख़ुद या अपने ही वंशज के नीचे) */
    public static function wouldCycle(int $id, ?int $newParent): bool
    {
        $pid = $newParent;
        $guard = 0;
        while ($pid !== null && $guard++ < 20) {
            if ($pid === $id) {
                return true;
            }
            $v = db()->value('SELECT parent_id FROM {p}locations WHERE id = ?', [$pid]);
            $pid = $v === null ? null : (int) $v;
        }
        return false;
    }

    /** slug/parent बदलने के बाद सभी वंशजों का path दोबारा (ट्रांज़ैक्शन के अंदर बुलाएँ) */
    public static function rebuildChildren(int $id, int $depth = 0): int
    {
        if ($depth > 10) {
            return 0;
        }
        $n = 0;
        foreach (db()->all('SELECT id, type, slug FROM {p}locations WHERE parent_id = ?', [$id]) as $c) {
            db()->query('UPDATE {p}locations SET path = ? WHERE id = ?', [self::pathFor($id, $c['type'], $c['slug']), $c['id']]);
            $n += 1 + self::rebuildChildren((int) $c['id'], $depth + 1);
        }
        return $n;
    }

    /** स्लग: अंग्रेज़ी नाम से, वरना हिंदी नाम से (लिप्यंतरण) */
    public static function slugFrom(string $given, ?string $nameEn, string $name): string
    {
        foreach ([$given, (string) $nameEn, $name] as $src) {
            $s = Str::slug($src, 100);
            if ($s !== '') {
                return $s;
            }
        }
        return 'location';
    }

    /** पहले स्तर (राज्य) का path वेबसाइट के अपने रास्तों से टकराए नहीं */
    public static function reservedPath(?string $path): bool
    {
        return $path !== null && !str_contains($path, '/') && in_array($path, TaxonomyService::RESERVED, true);
    }

    /** नाम/अंग्रेज़ी नाम/path से खोज; हर नतीजे के साथ ऊपर वाले का नाम ("महराजगंज, उत्तर प्रदेश") */
    public static function search(string $q, array $types = [], bool $activeOnly = false, int $limit = 20): array
    {
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(l.name LIKE ? OR l.name_en LIKE ? OR l.path LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if ($types) {
            $where[] = 'l.type IN (' . \App\Core\Database::in($types) . ')';
            array_push($params, ...$types);
        }
        if ($activeOnly) {
            $where[] = "l.status = 'active'";
        }
        $prefix = $q !== '' ? addcslashes($q, '%_\\') . '%' : '';
        $rows = db()->all(
            'SELECT l.id, l.name, l.name_en, l.type, l.path, l.is_popular, p.name AS parent_name
             FROM {p}locations l LEFT JOIN {p}locations p ON p.id = l.parent_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ' . ($q !== '' ? '(l.name LIKE ? OR l.name_en LIKE ?) DESC, ' : '') . "l.is_popular DESC, FIELD(l.type, 'state','district','city','tehsil','division','block','locality','country'), l.name
             LIMIT " . max(1, min($limit, 50)),
            $q !== '' ? [...$params, $prefix, $prefix] : $params
        );
        foreach ($rows as &$r) {
            $r['label'] = $r['name'] . ($r['parent_name'] && $r['type'] !== 'state' ? ', ' . $r['parent_name'] : '');
            $r['type_label'] = Location::SHORT[$r['type']] ?? $r['type'];
        }
        return $rows;
    }

    /** पाठक का चुना शहर (कुकी my_city में सिर्फ़ ID) */
    public static function myCity(): ?array
    {
        static $city = false;
        if ($city === false) {
            $id = (int) ($_COOKIE['my_city'] ?? 0);
            $city = $id > 0 ? db()->first(
                "SELECT id, name, name_en, type, path FROM {p}locations WHERE id = ? AND status = 'active' AND type IN ('" . implode("','", self::MY_CITY_TYPES) . "')",
                [$id]
            ) : null;
        }
        return $city;
    }

    /**
     * CSV इम्पोर्ट। कॉलम: type,name,name_en,slug,parent,code,is_popular
     * parent = ऊपर वाले का path (uttar-pradesh) या ID; ख़ाली = देश (भारत) के नीचे।
     * एक भी पंक्ति ग़लत हो तो कुछ नहीं जुड़ता (ट्रांज़ैक्शन)।
     * @return array{ok: bool, created: int, skipped: int, errors: string[]}
     */
    public static function import(string $csv): array
    {
        if (strlen($csv) > self::IMPORT_MAX_BYTES) {
            return ['ok' => false, 'created' => 0, 'skipped' => 0, 'errors' => ['फ़ाइल 2 MB से बड़ी है।']];
        }
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv); // Excel का BOM
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        $header = array_map(static fn($h) => strtolower(trim($h)), str_getcsv((string) array_shift($lines), ',', '"', '\\'));
        foreach (['type', 'name'] as $need) {
            if (!in_array($need, $header, true)) {
                return ['ok' => false, 'created' => 0, 'skipped' => 0, 'errors' => ["पहली पंक्ति में कॉलम “{$need}” ज़रूरी है। नमूना फ़ाइल डाउनलोड करके देखें।"]];
            }
        }
        if (count($lines) > self::IMPORT_MAX_ROWS) {
            return ['ok' => false, 'created' => 0, 'skipped' => 0, 'errors' => ['एक बार में ज़्यादा से ज़्यादा ' . self::IMPORT_MAX_ROWS . ' पंक्तियाँ।']];
        }
        $country = db()->value("SELECT id FROM {p}locations WHERE type = 'country' ORDER BY id LIMIT 1");
        $errors = [];
        $created = $skipped = 0;
        try {
            db()->transaction(static function () use ($lines, $header, $country, &$errors, &$created, &$skipped) {
                foreach ($lines as $n => $line) {
                    $lineNo = $n + 2;
                    if (trim($line) === '') {
                        continue;
                    }
                    $cells = str_getcsv($line, ',', '"', '\\');
                    $row = [];
                    foreach ($header as $i => $h) {
                        $row[$h] = trim((string) ($cells[$i] ?? ''));
                    }
                    $type = strtolower($row['type']);
                    $name = mb_substr(strip_tags($row['name']), 0, 120);
                    if (!isset(Location::TYPES[$type]) || $type === 'country') {
                        $errors[] = "पंक्ति $lineNo: प्रकार “{$row['type']}” मान्य नहीं (state, division, district, tehsil, block, city, locality)।";
                        continue;
                    }
                    if ($name === '') {
                        $errors[] = "पंक्ति $lineNo: नाम ख़ाली है।";
                        continue;
                    }
                    $parentRef = $row['parent'] ?? '';
                    $parent = $parentRef === '' ? ($country ? ['id' => $country, 'type' => 'country'] : null)
                        : (ctype_digit($parentRef) ? db()->first('SELECT id, type FROM {p}locations WHERE id = ?', [(int) $parentRef])
                        : db()->first('SELECT id, type FROM {p}locations WHERE path = ?', [strtolower($parentRef)]));
                    if (!$parent) {
                        $errors[] = "पंक्ति $lineNo: ऊपर वाली लोकेशन “{$parentRef}” नहीं मिली।";
                        continue;
                    }
                    if (!in_array($type, Location::childTypes($parent['type']), true)) {
                        $errors[] = "पंक्ति $lineNo: " . Location::SHORT[$parent['type']] . ' के नीचे ' . Location::SHORT[$type] . ' नहीं आ सकता।';
                        continue;
                    }
                    $nameEn = mb_substr(strip_tags($row['name_en'] ?? ''), 0, 120) ?: null;
                    $slug = self::slugFrom($row['slug'] ?? '', $nameEn, $name);
                    $path = self::pathFor((int) $parent['id'], $type, $slug);
                    if (self::reservedPath($path)) {
                        $errors[] = "पंक्ति $lineNo: “{$slug}” वेबसाइट का आरक्षित शब्द है, दूसरा स्लग दें।";
                        continue;
                    }
                    $dupe = $path !== null
                        ? db()->value('SELECT id FROM {p}locations WHERE path = ?', [$path])
                        : db()->value('SELECT id FROM {p}locations WHERE parent_id = ? AND slug = ?', [$parent['id'], $slug]);
                    if ($dupe) {
                        $skipped++;
                        continue;
                    }
                    Location::create([
                        'parent_id' => (int) $parent['id'], 'type' => $type, 'name' => $name, 'name_en' => $nameEn, 'slug' => $slug, 'path' => $path,
                        'code' => mb_substr($row['code'] ?? '', 0, 20) ?: null, 'is_popular' => in_array(strtolower($row['is_popular'] ?? ''), ['1', 'yes', 'हाँ'], true) ? 1 : 0,
                        'sort_order' => (int) db()->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {p}locations WHERE parent_id = ?', [$parent['id']]),
                    ]);
                    $created++;
                }
                if ($errors) {
                    throw new \RuntimeException('import-failed');
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'import-failed') {
                throw $e;
            }
            return ['ok' => false, 'created' => 0, 'skipped' => 0, 'errors' => array_slice($errors, 0, 30)];
        }
        return ['ok' => true, 'created' => $created, 'skipped' => $skipped, 'errors' => []];
    }
}
