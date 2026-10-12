<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin token lives in an HttpOnly cookie, so JavaScript (and therefore XSS) can never read it.
 * This middleware copies it into the Authorization header so Sanctum keeps working unchanged.
 *
 * Because browsers attach cookies automatically, state-changing requests that rely on the cookie
 * must also prove they come from our own front-end (CSRF defence in depth, on top of SameSite=Strict):
 *   - the custom header  X-Requested-With: XMLHttpRequest  (cross-site pages cannot send it), and
 *   - an Origin header, when present, that is one of the allowed front-end origins.
 */
class CookieAuth
{
    public const PLAIN = 'admin_token';
    public const SECURE = '__Host-admin_token';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie(self::SECURE) ?: $request->cookie(self::PLAIN);

        if (is_string($token) && $token !== '' && ! $request->bearerToken()) {
            if (! $request->isMethodSafe()) {
                $this->assertOwnFrontend($request);
            }
            $request->headers->set('Authorization', 'Bearer '.$token);
        }

        return $next($request);
    }

    public static function cookieName(Request $request): string
    {
        return $request->isSecure() ? self::SECURE : self::PLAIN;
    }

    private function assertOwnFrontend(Request $request): void
    {
        if ($request->header('X-Requested-With') !== 'XMLHttpRequest') {
            abort(403, 'Invalid request.');
        }

        $origin = $request->headers->get('Origin');
        if ($origin !== null && ! in_array(rtrim($origin, '/'), (array) config('cors.allowed_origins'), true)) {
            abort(403, 'Invalid request origin.');
        }
    }
}
