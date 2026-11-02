<?php

namespace App\Http\Middleware;

class SecurityHeaders
{
    public function handle($request, \Closure $next)
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        if (!$response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'; object-src 'none'; base-uri 'self'");
        }
        return $response;
    }
}
