<?php
declare(strict_types=1);

namespace App\Core;

/** हर middleware यह इंटरफ़ेस लागू करता है */
interface Middleware
{
    public function handle(Request $request, \Closure $next, string ...$params): Response;
}
