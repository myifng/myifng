<?php
declare(strict_types=1);

namespace App\Core;

/** config/*.php फ़ाइलें पढ़ता है: config('app.name') */
final class Config
{
    private array $items = [];

    public function __construct(private string $dir)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);
        if (!array_key_exists($file, $this->items)) {
            $path = $this->dir . '/' . $file . '.php';
            $this->items[$file] = is_file($path) ? (require $path) : [];
        }
        $value = $this->items[$file];
        foreach ($parts as $p) {
            if (!is_array($value) || !array_key_exists($p, $value)) {
                return $default;
            }
            $value = $value[$p];
        }
        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $ref = &$this->items;
        foreach ($parts as $p) {
            if (!isset($ref[$p]) || !is_array($ref[$p])) {
                $ref[$p] = [];
            }
            $ref = &$ref[$p];
        }
        $ref = $value;
    }
}
