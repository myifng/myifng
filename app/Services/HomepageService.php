<?php
declare(strict_types=1);

namespace App\Services;

/**
 * होमपेज बिल्डर: ब्लॉक रजिस्ट्री (config/home_blocks.php), सेक्शन की सेटिंग साफ़ करना, कैश।
 * Phase 5 में वेबसाइट का होमपेज इन्हीं सेक्शन से बनेगा (कुछ भी हार्डकोड नहीं)।
 */
final class HomepageService
{
    public static function blocks(): array
    {
        return (array) config('home_blocks', []);
    }

    public static function block(string $type): ?array
    {
        return self::blocks()[$type] ?? null;
    }

    /** सभी सेक्शन, क्रम में, settings डिकोड करके */
    public static function sections(bool $onlyActive = false): array
    {
        $rows = db()->all('SELECT * FROM {p}home_sections' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, id');
        foreach ($rows as &$r) {
            $r['settings'] = json_decode((string) $r['settings'], true) ?: [];
        }
        return $rows;
    }

    /** ब्लॉक की सामग्री वाला मॉड्यूल तैयार है? नहीं तो किस phase में */
    public static function readiness(array $block): ?int
    {
        if (empty($block['needs'])) {
            return null;
        }
        $phase = (int) (config('modules.modules.' . $block['needs'] . '.phase') ?? 99);
        return $phase > (int) config('app.phase') ? $phase : null;
    }

    /** फ़ॉर्म से आई सेटिंग को schema के हिसाब से साफ़ करें (मनमाने key नहीं) */
    public static function normalize(string $type, array $input): array
    {
        $block = self::block($type) ?? [];
        $out = [];
        foreach ($block['fields'] ?? [] as $name => $f) {
            $v = $input[$name] ?? null;
            $out[$name] = match ($f['type']) {
                'number' => max((int) ($f['min'] ?? 1), min((int) ($f['max'] ?? 100), (int) ($v ?? $f['default'] ?? 1))),
                'select' => isset($f['options'][(string) $v]) ? (string) $v : (string) ($f['default'] ?? array_key_first($f['options'])),
                'switch' => in_array($v, ['1', 1, 'on', true], true) ? 1 : 0,
                'category', 'location' => ctype_digit((string) $v) ? (int) $v : null,
                'poll' => (string) (int) $v === '0' ? '' : (string) (int) $v,
                'adslot' => preg_match('/^[a-z0-9_-]{1,60}$/', (string) $v) ? (string) $v : (string) ($f['default'] ?? ''),
                'stories' => array_slice(array_values(array_filter(array_map('intval', is_array($v) ? $v : preg_split('/[\s,]+/', (string) $v)))), 0, 30),
                'code' => mb_substr((string) $v, 0, 20000),
                default => mb_substr(trim(strip_tags((string) $v)), 0, 300),
            };
        }
        return $out;
    }

    public static function defaults(string $type): array
    {
        $out = [];
        foreach ((self::block($type)['fields'] ?? []) as $name => $f) {
            if (array_key_exists('default', $f)) {
                $out[$name] = $f['default'];
            }
        }
        return $out;
    }

    public static function clearCache(): void
    {
        cache()->flush('home');
    }
}
