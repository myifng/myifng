<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/** एक IP से बहुत ज़्यादा अनुरोध रोकें: throttle:5,10 = 10 मिनट में 5 POST */
final class ThrottleMiddleware implements Middleware
{
    public function handle(Request $request, \Closure $next, string ...$params): Response
    {
        if (!$request->isPost()) {
            return $next($request);
        }
        $max = (int) ($params[0] ?? 10);
        $minutes = (int) ($params[1] ?? 1);
        $key = 'throttle.' . sha1($request->ip() . '|' . $request->path());
        $hits = array_filter((array) cache()->get($key, []), static fn($t) => $t > time() - $minutes * 60);
        if (count($hits) >= $max) {
            throw new HttpException(429);
        }
        $hits[] = time();
        cache()->set($key, array_values($hits), $minutes * 60);
        return $next($request);
    }
}
