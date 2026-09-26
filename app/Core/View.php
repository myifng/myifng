<?php
declare(strict_types=1);

namespace App\Core;

/**
 * व्यू: app/Views/*.php टेम्पलेट। टेम्पलेट में सिर्फ़ HTML और e() से escape किया आउटपुट।
 * लेआउट: $this->layout('layouts/admin'); सेक्शन: $this->start('scripts') ... $this->stop();
 */
final class View
{
    private ?string $layout = null;
    private array $sections = [];
    private array $stack = [];
    private array $shared = [];

    public function __construct(private string $dir)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function render(string $template, array $data = []): string
    {
        $this->layout = null;
        $data = array_merge($this->shared, $data);
        $content = $this->capture($template, $data);
        // टेम्पलेट ने लेआउट चुना हो तो उसके अंदर रखें (लेआउट भी दूसरा लेआउट चुन सकता है)
        while ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            $this->sections['content'] = $content;
            $content = $this->capture($layout, $data);
        }
        return $content;
    }

    private function capture(string $template, array $data): string
    {
        $file = $this->dir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("व्यू नहीं मिला: $template");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public function layout(string $name): void
    {
        $this->layout = $name;
    }

    public function start(string $name): void
    {
        $this->stack[] = $name;
        ob_start();
    }

    public function stop(): void
    {
        $name = array_pop($this->stack);
        $this->sections[$name] = ($this->sections[$name] ?? '') . ob_get_clean();
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /** छोटा हिस्सा (partial) शामिल करें */
    public function insert(string $template, array $data = []): string
    {
        return $this->capture($template, array_merge($this->shared, $data));
    }
}
