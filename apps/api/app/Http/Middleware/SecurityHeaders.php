<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds security-relevant HTTP headers to every response.
 *
 * Applied globally in bootstrap/app.php so all routes benefit.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options',  'nosniff');
        $response->headers->set('X-Frame-Options',         'DENY');
        $response->headers->set('Referrer-Policy',         'strict-origin-when-cross-origin');
        $response->headers->set('X-XSS-Protection',        '1; mode=block');
        $response->headers->set('Permissions-Policy',      'camera=(), microphone=(), geolocation=()');

        // Remove fingerprinting headers.
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
