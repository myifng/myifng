<?php
declare(strict_types=1);

namespace App\Services;

/** वेबसाइट सेटिंग: डेटाबेस + फ़ाइल कैश। बदलते ही कैश साफ़। */
final class SettingService
{
    private const CACHE_KEY = 'settings.all';

    /** सभी सेटिंग: डेटाबेस के मान, न हों तो schema के डिफ़ॉल्ट */
    public static function all(): array
    {
        return cache()->remember(self::CACHE_KEY, 3600, static function (): array {
            $out = SettingsSchema::defaults();
            foreach (db()->all('SELECT name, value FROM {p}settings') as $r) {
                $out[$r['name']] = $r['value'];
            }
            return $out;
        });
    }

    public static function set(string $name, mixed $value, string $group = 'general', string $type = 'text'): void
    {
        db()->query(
            'INSERT INTO {p}settings (name, value, group_name, type, updated_at) VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()',
            [$name, is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value, $group, $type]
        );
        self::clear();
    }

    public static function setMany(array $values, string $group = 'general'): void
    {
        foreach ($values as $k => $v) {
            self::set($k, $v, $group);
        }
    }

    public static function clear(): void
    {
        cache()->forget(self::CACHE_KEY);
        setting('__reload__');
    }
}
