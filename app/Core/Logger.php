<?php
declare(strict_types=1);

namespace App\Core;

/** storage/logs में रोज़ की लॉग फ़ाइल */
final class Logger
{
    public function __construct(private string $dir)
    {
    }

    public function log(string $level, string $message, array $context = [], string $channel = 'app'): void
    {
        if (!is_dir($this->dir) || !is_writable($this->dir)) {
            return;
        }
        $line = sprintf("[%s] %s: %s%s\n", date('Y-m-d H:i:s'), strtoupper($level), $message, $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');
        @file_put_contents($this->dir . '/' . $channel . '-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public function error(string $m, array $c = []): void { $this->log('error', $m, $c); }
    public function warning(string $m, array $c = []): void { $this->log('warning', $m, $c); }
    public function info(string $m, array $c = []): void { $this->log('info', $m, $c); }
}
