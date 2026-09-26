<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/** लॉगिन ज़रूरी। न हो तो लॉगिन पेज पर, और बाद में वापस उसी पेज पर। */
final class AuthMiddleware implements Middleware
{
    public function handle(Request $request, \Closure $next, string ...$params): Response
    {
        if (!auth()->check()) {
            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'message' => 'सेशन ख़त्म हो गया। दोबारा लॉगिन करें।'], 401);
            }
            if ($request->method() === 'GET') {
                app('session')->set('intended', $request->fullUrl());
            }
            return Response::redirect(route('admin.login'));
        }
        return $next($request);
    }
}
