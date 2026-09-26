<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/** रूट पर अनुमति: ->middleware('can:users.edit'); कई दें तो कोई एक काफ़ी: can:news.edit,news.approve */
final class PermissionMiddleware implements Middleware
{
    public function handle(Request $request, \Closure $next, string ...$params): Response
    {
        if (!app('gate')->canAny($params)) {
            throw new HttpException(403);
        }
        return $next($request);
    }
}
