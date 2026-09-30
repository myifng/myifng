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
            // रिपोर्टर का सेशन ख़त्म हो तो रिपोर्टर लॉगिन पेज पर (एडमिन पता न दिखे)
            $reporter = ($_COOKIE['np_portal'] ?? '') === 'reporter' && setting('separate_reporter_login', '1') === '1';
            return Response::redirect(route($reporter ? 'reporter.login' : 'admin.login'));
        }
        // Phase 15: एडमिन IP allowlist (सिर्फ़ स्टाफ़ पर; रिपोर्टर पर नहीं)
        if (!\App\Services\SecurityService::staffIpOk($request->ip(), auth()->user()['role_slug'] ?? null)) {
            \App\Services\AuditService::log('ip_blocked', 'auth', auth()->id(), 'allowlist से बाहर के IP से एडमिन: ' . $request->ip());
            auth()->logout();
            throw new \App\Core\HttpException(403, 'इस IP से एडमिन की अनुमति नहीं है।');
        }
        return $next($request);
    }
}
