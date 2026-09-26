<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Migrator;
use App\Core\Request;
use App\Core\Response;

/**
 * नया वर्ज़न अपलोड हुआ पर डेटाबेस अपडेट (migration) बाकी है:
 * Super Admin को "अपडेट करें" पेज, बाकी स्टाफ़ को 503। अधूरे डेटाबेस पर कोई पेज न चले।
 */
final class UpToDateMiddleware implements Middleware
{
    public function handle(Request $request, \Closure $next, string ...$params): Response
    {
        $pending = (new Migrator(db(), BASE_PATH . '/database/migrations'))->pending();
        if (!$pending) {
            return $next($request);
        }
        if (!is_super_admin()) {
            throw new HttpException(503, 'सिस्टम अपडेट बाकी है। Super Admin के अपडेट चलाने तक एडमिन पैनल उपलब्ध नहीं है।');
        }
        return new Response(app('view')->render('admin/system/update', ['pending' => array_map(static fn($f) => basename($f, '.php'), $pending)]), 503);
    }
}
