<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/** मेंटेनेंस मोड: वेबसाइट पर 503, लेकिन लॉगिन स्टाफ़ को साइट दिखती रहे */
final class MaintenanceMiddleware implements Middleware
{
    public function handle(Request $request, \Closure $next, string ...$params): Response
    {
        if (setting('maintenance_mode') === '1' && !auth()->check()) {
            throw new HttpException(503, (string) setting('maintenance_message', ''));
        }
        return $next($request);
    }
}
