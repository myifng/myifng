<?php
declare(strict_types=1);

namespace App\Services;

/** config/settings.php का schema: टैब, खाने, डिफ़ॉल्ट मान, नियम */
final class SettingsSchema
{
    public static function tabs(): array
    {
        return (array) config('settings', []);
    }

    public static function tab(string $key): ?array
    {
        return self::tabs()[$key] ?? null;
    }

    /** सभी खानों के डिफ़ॉल्ट मान */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::tabs() as $tab) {
            foreach ($tab['fields'] as $name => $f) {
                if (array_key_exists('default', $f)) {
                    $out[$name] = (string) $f['default'];
                }
            }
        }
        return $out;
    }

    /** मौजूदा यूज़र यह टैब बदल सकता है? */
    public static function canEdit(array $tab): bool
    {
        return can('settings.' . (($tab['permission'] ?? 'edit') === 'manage' ? 'manage' : 'edit'));
    }

    /** वैलिडेशन नियम और लेबल (image/switch/checkboxes अलग से संभाले जाते हैं) */
    public static function rules(array $tab): array
    {
        $rules = [];
        $labels = [];
        foreach ($tab['fields'] as $name => $f) {
            $labels[$name] = $f['label'];
            if (!empty($f['rules'])) {
                $rules[$name] = $f['rules'];
            } elseif (in_array($f['type'], ['select', 'font'], true) && !empty($f['options'])) {
                $rules[$name] = 'nullable|in:' . implode(',', array_keys($f['options']));
            } elseif ($f['type'] === 'timezone') {
                $rules[$name] = 'required';
            }
        }
        return [$rules, $labels];
    }
}
