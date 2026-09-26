<?php
/** डेटाबेस कनेक्शन (मान env.php से) */
$env = is_file(__DIR__ . '/env.php') ? require __DIR__ . '/env.php' : [];

return [
    'host'   => (string) ($env['DB_HOST'] ?? 'localhost'),
    'port'   => (int) ($env['DB_PORT'] ?? 3306),
    'name'   => (string) ($env['DB_NAME'] ?? ''),
    'user'   => (string) ($env['DB_USER'] ?? ''),
    'pass'   => (string) ($env['DB_PASS'] ?? ''),
    'prefix' => (string) ($env['DB_PREFIX'] ?? ''),
];
