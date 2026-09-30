<?php
/** मुख्य कॉन्फ़िगरेशन। संवेदनशील मान (DB पासवर्ड आदि) env.php में रहते हैं, जिसे इंस्टॉलर बनाता है। */
$env = is_file(__DIR__ . '/env.php') ? require __DIR__ . '/env.php' : [];

return [
    'version'      => '1.11.0',
    'phase'        => 12,                                  // अभी तक पूरे हुए phase; साइडबार इसी तक के मॉड्यूल दिखाता है
    'url'          => rtrim((string) ($env['APP_URL'] ?? ''), '/'),
    'key'          => (string) ($env['APP_KEY'] ?? ''),
    'debug'        => (bool) ($env['DEBUG'] ?? false),
    'timezone'     => (string) ($env['TIMEZONE'] ?? 'Asia/Kolkata'),
    'admin_path'   => trim((string) ($env['ADMIN_PATH'] ?? 'admin'), '/'),
    'session_name' => 'nsess_' . substr(md5((string) ($env['APP_KEY'] ?? 'x')), 0, 6),
    'cache'        => (bool) ($env['CACHE'] ?? true),

    // हर अनुरोध पर चलने वाले middleware
    'global_middleware' => ['csrf', 'scheduler'],

    // middleware के छोटे नाम
    'middleware' => [
        'csrf'        => App\Middleware\CsrfMiddleware::class,
        'auth'        => App\Middleware\AuthMiddleware::class,
        'guest'       => App\Middleware\GuestMiddleware::class,
        'can'         => App\Middleware\PermissionMiddleware::class,
        'maintenance' => App\Middleware\MaintenanceMiddleware::class,
        'throttle'    => App\Middleware\ThrottleMiddleware::class,
        'uptodate'    => App\Middleware\UpToDateMiddleware::class,
        'scheduler'   => App\Middleware\SchedulerMiddleware::class,
    ],
];
