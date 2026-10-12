<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Baseline hardening headers for every response the API sends. */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'no-referrer');
        $h->set('X-Frame-Options', 'SAMEORIGIN'); // SAMEORIGIN: the site embeds PDF attachments in an iframe
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // JSON never needs to load anything.
        if (str_contains((string) $h->get('Content-Type'), 'json')) {
            $h->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'self'");
        }

        // Login / session / admin responses must never be cached by browsers or proxies.
        if ($request->is('api/auth/*', 'api/admin/*')) {
            $h->set('Cache-Control', 'no-store, private');
            $h->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
