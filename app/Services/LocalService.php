<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * एडवांस्ड लोकल न्यूज़ (Phase 14): ज़िला/राज्य डायरेक्टरी (ख़बरों की गिनती के साथ), मेरा शहर, आसपास,
 * यहाँ के रिपोर्टर, इलाक़े की मुख्य ख़बर, और एडमिन के लिए कवरेज (कहाँ ख़बरें नहीं आ रहीं)।
 */
final class LocalService
{
    /** सारी सक्रिय लोकेशन: id => [parent, type, name, path] (10 मिनट कैश) */
    public static function tree(): array
    {
        return cache()->remember('locations.flat', 600, static function (): array {
            $out = [];
            foreach (db()->all("SELECT id, parent_id, type, name, path, is_popular FROM {p}locations WHERE status = 'active'") as $l) {
                $out[(int) $l['id']] = ['parent' => (int) $l['parent_id'], 'type' => $l['type'], 'name' => $l['name'], 'path' => $l['path'], 'popular' => (int) $l['is_popular']];
            }
            return $out;
        });
    }

    /** किसी लोकेशन का ऊपर वाला दिए प्रकार का (जैसे शहर → ज़िला) */
    public static function ancestorOfType(int $id, string $type): ?int
    {
        $t = self::tree();
        for ($i = 0; $i < 10 && $id && isset($t[$id]); $i++) {
            if ($t[$id]['type'] === $type) {
                return $id;
            }
            $id = $t[$id]['parent'];
        }
        return null;
    }

    /** पिछले N दिन की प्रकाशित ख़बरें, ज़िले/राज्य तक जोड़कर: ['district' => [id => n], 'state' => [id => n]] */
    public static function counts(int $days = 7): array
    {
        return cache()->remember('locations.counts.' . $days, 600, static function () use ($days): array {
            $out = ['district' => [], 'state' => []];
            foreach (db()->all('SELECT n.location_id id, COUNT(*) c FROM {p}news n WHERE ' . NewsQuery::PUBLISHED . ' AND n.location_id IS NOT NULL AND n.published_at >= NOW() - INTERVAL ? DAY GROUP BY n.location_id', [$days]) as $r) {
                foreach (['district', 'state'] as $type) {
                    if ($a = self::ancestorOfType((int) $r['id'], $type)) {
                        $out[$type][$a] = ($out[$type][$a] ?? 0) + (int) $r['c'];
                    }
                }
            }
            return $out;
        });
    }

    /** राज्य (ख़बरों की गिनती के साथ) */
    public static function states(): array
    {
        $c = self::counts(7)['state'];
        $rows = [];
        foreach (self::tree() as $id => $l) {
            if ($l['type'] === 'state' && $l['path']) {
                $rows[] = ['id' => $id, 'name' => $l['name'], 'path' => $l['path'], 'count' => $c[$id] ?? 0];
            }
        }
        usort($rows, static fn($a, $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);
        return $rows;
    }

    /** राज्य के ज़िले: गिनती, आख़िरी ख़बर */
    public static function districts(int $stateId): array
    {
        $c = self::counts(7)['district'];
        $rows = [];
        foreach (self::tree() as $id => $l) {
            if ($l['type'] === 'district' && $l['path'] && self::ancestorOfType($id, 'state') === $stateId) {
                $rows[$id] = ['id' => $id, 'name' => $l['name'], 'path' => $l['path'], 'popular' => $l['popular'], 'count' => $c[$id] ?? 0, 'latest' => null];
            }
        }
        // हर ज़िले की ताज़ा ख़बर (सिर्फ़ जिनकी गिनती > 0), एक क्वेरी में
        $active = array_keys(array_filter($rows, static fn($r) => $r['count'] > 0));
        if ($active) {
            $map = [];
            foreach (self::tree() as $id => $l) {
                if (($d = self::ancestorOfType($id, 'district')) && in_array($d, $active, true)) {
                    $map[$id] = $d;
                }
            }
            $ids = array_keys($map);
            foreach (db()->all('SELECT n.id, n.title, n.slug, n.published_at, n.location_id FROM {p}news n WHERE ' . NewsQuery::PUBLISHED . ' AND n.location_id IN (' . Database::in($ids) . ') AND n.published_at >= NOW() - INTERVAL 7 DAY ORDER BY n.published_at DESC LIMIT 500', $ids) as $n) {
                $d = $map[(int) $n['location_id']];
                $rows[$d]['latest'] ??= $n;
            }
        }
        $rows = array_values($rows);
        usort($rows, static fn($a, $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);
        return $rows;
    }

    /** आसपास: उसी ऊपर वाले के दूसरे इलाक़े (शहर हो तो उसका ज़िला भी) */
    public static function nearby(array $loc, int $limit = 12): array
    {
        $t = self::tree();
        $parent = (int) ($loc['parent_id'] ?? ($t[(int) $loc['id']]['parent'] ?? 0));
        $out = [];
        if ($parent && isset($t[$parent]) && $t[$parent]['path'] && $t[$parent]['type'] !== 'country') {
            $out[] = ['id' => $parent, 'name' => $t[$parent]['name'], 'path' => $t[$parent]['path'], 'parent' => true];
        }
        foreach ($t as $id => $l) {
            if ($l['parent'] === $parent && $id !== (int) $loc['id'] && $l['path'] && count($out) < $limit) {
                $out[] = ['id' => $id, 'name' => $l['name'], 'path' => $l['path'], 'parent' => false];
            }
        }
        return $out;
    }

    /** इस इलाक़े (और नीचे) के सक्रिय रिपोर्टर */
    public static function reporters(int $locationId, int $limit = 8): array
    {
        $ids = NewsQuery::descendants($locationId);
        $in = Database::in($ids);
        return db()->all("SELECT u.id, u.name, u.avatar, r.designation, r.photo, l.name area FROM {p}reporters r JOIN {p}users u ON u.id = r.user_id LEFT JOIN {p}locations l ON l.id = COALESCE(r.area_location_id, r.district_id)
            WHERE r.status = 'active' AND (r.valid_until IS NULL OR r.valid_until >= CURDATE()) AND (r.area_location_id IN ($in) OR r.district_id IN ($in))
            ORDER BY r.designation LIKE '%ब्यूरो%' DESC, u.name LIMIT " . max(1, $limit), [...$ids, ...$ids]);
    }

    /** इलाक़े की मुख्य ख़बर: 48 घंटे की ब्रेकिंग/फ़ीचर्ड, वरना सबसे नई */
    public static function topStory(int $locationId): ?array
    {
        [$w, $p] = NewsQuery::locationWhere($locationId);
        return NewsQuery::list("$w AND (n.is_breaking = 1 OR n.is_featured = 1) AND n.published_at >= NOW() - INTERVAL 48 HOUR", $p, 1)[0] ?? null;
    }

    /** इलाक़े के वीडियो/फ़ोटो */
    public static function media(int $locationId, int $limit = 6): array
    {
        $ids = NewsQuery::descendants($locationId);
        $in = Database::in($ids);
        $v = db()->all("SELECT 'video' kind, x.title, x.slug, x.cover image, x.published_at FROM {p}videos x WHERE " . MultimediaService::published('x') . " AND x.location_id IN ($in) ORDER BY x.published_at DESC LIMIT $limit", $ids);
        $g = db()->all("SELECT 'gallery' kind, x.title, x.slug, x.cover image, x.published_at FROM {p}galleries x WHERE " . MultimediaService::published('x') . " AND x.location_id IN ($in) ORDER BY x.published_at DESC LIMIT $limit", $ids);
        $all = array_merge($v, $g);
        usort($all, static fn($a, $b) => strcmp((string) $b['published_at'], (string) $a['published_at']));
        return array_slice($all, 0, $limit);
    }

    /**
     * एडमिन: ज़िलों का कवरेज: 7/30 दिन की ख़बरें, आख़िरी ख़बर कब, सक्रिय रिपोर्टर, समीक्षा में।
     * "गैप" = 30 दिन में 0 ख़बर या आख़िरी ख़बर 7 दिन से पुरानी।
     */
    public static function coverage(?int $stateId = null): array
    {
        $c7 = self::counts(7)['district'];
        $c30 = self::counts(30)['district'];
        $t = self::tree();
        $last = [];
        $pending = [];
        foreach (db()->all("SELECT n.location_id id, MAX(n.published_at) last FROM {p}news n WHERE n.status = 'published' AND n.deleted_at IS NULL AND n.location_id IS NOT NULL GROUP BY n.location_id") as $r) {
            if ($d = self::ancestorOfType((int) $r['id'], 'district')) {
                $last[$d] = max($last[$d] ?? '', (string) $r['last']);
            }
        }
        foreach (db()->all("SELECT n.location_id id, COUNT(*) c FROM {p}news n WHERE n.status IN ('submitted','review','fact_check') AND n.deleted_at IS NULL AND n.location_id IS NOT NULL GROUP BY n.location_id") as $r) {
            if ($d = self::ancestorOfType((int) $r['id'], 'district')) {
                $pending[$d] = ($pending[$d] ?? 0) + (int) $r['c'];
            }
        }
        $rep = [];
        foreach (db()->all("SELECT COALESCE(district_id, area_location_id) loc, COUNT(*) c FROM {p}reporters WHERE status = 'active' AND (valid_until IS NULL OR valid_until >= CURDATE()) GROUP BY loc") as $r) {
            if ($r['loc'] && ($d = self::ancestorOfType((int) $r['loc'], 'district'))) {
                $rep[$d] = ($rep[$d] ?? 0) + (int) $r['c'];
            }
        }
        $rows = [];
        foreach ($t as $id => $l) {
            if ($l['type'] !== 'district') {
                continue;
            }
            $st = self::ancestorOfType($id, 'state');
            if ($stateId && $st !== $stateId) {
                continue;
            }
            $lastAt = $last[$id] ?? null;
            $rows[] = ['id' => $id, 'name' => $l['name'], 'path' => $l['path'], 'state' => $st ? ($t[$st]['name'] ?? '') : '', 'd7' => $c7[$id] ?? 0, 'd30' => $c30[$id] ?? 0,
                'last' => $lastAt, 'reporters' => $rep[$id] ?? 0, 'pending' => $pending[$id] ?? 0,
                'gap' => !($c30[$id] ?? 0) || !$lastAt || strtotime($lastAt) < time() - 7 * 86400];
        }
        usort($rows, static fn($a, $b) => [$b['gap'], $a['d7'], $a['name']] <=> [$a['gap'], $b['d7'], $b['name']]);
        return $rows;
    }
}
