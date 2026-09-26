<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/** हर POST/PUT/DELETE अनुरोध में सही CSRF टोकन ज़रूरी; ग़लत पर 419 */
final class CsrfMiddleware implements Middleware
{
    public function handle(Request $request, \Closure $next, string ...$params): Response
    {
        if ($request->isPost()) {
            $token = $request->post()['_csrf'] ?? $request->header('X-CSRF-Token');
            if (!Csrf::verify(is_string($token) ? $token : null)) {
                throw new HttpException(419);
            }
        }
        return $next($request);
    }
}
