<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/** पहले से लॉगिन हो तो लॉगिन पेज की जगह डैशबोर्ड */
final class GuestMiddleware implements Middleware
{
    public function handle(Request $request, \Closure $next, string ...$params): Response
    {
        if (auth()->check()) {
            return Response::redirect(route('admin.dashboard'));
        }
        return $next($request);
    }
}
