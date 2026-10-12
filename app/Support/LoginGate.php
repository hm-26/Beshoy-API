<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * "Secret login link" gate.
 *
 * The admin login page is only usable after the visitor opened  /0xGlitch?secret=<ADMIN_LOGIN_SECRET>.
 * The front-end sends that secret once to POST /api/auth/gate; if it matches, the API answers with a short-lived,
 * signed, HttpOnly, SameSite=Strict cookie. POST /api/auth/login refuses (404) every request without that cookie.
 */
class LoginGate
{
    public const PLAIN = 'login_gate';
    public const SECURE = '__Host-login_gate';

    public const MIN_SECRET_LENGTH = 32;
    public const MAX_LOGIN_FAILURES = 5;   // wrong passwords allowed per opened gate

    /** A secret shorter than 32 chars is treated as "not configured" => login stays locked (guessing protection). */
    public static function secret(): string
    {
        $secret = (string) config('auth.login_secret', '');

        return strlen($secret) >= self::MIN_SECRET_LENGTH ? $secret : '';
    }

    public static function minutes(): int
    {
        return max(1, min(60, (int) config('auth.login_gate_minutes', 10)));
    }

    /** Constant-time comparison. An empty configured secret locks the login for everybody. */
    public static function secretMatches(?string $given): bool
    {
        $secret = self::secret();

        if ($secret === '' || ! is_string($given) || $given === '') {
            return false;
        }

        // Hash both sides first so the comparison time never depends on the length of the guess.
        return hash_equals(hash('sha256', $secret), hash('sha256', $given));
    }

    public static function cookieName(Request $request): string
    {
        return $request->isSecure() ? self::SECURE : self::PLAIN;
    }

    private static function sign(string $payload, Request $request): string
    {
        // Keyed with APP_KEY *and* the secret (changing the secret invalidates every open gate) and bound to the
        // browser's User-Agent, so a copied cookie is useless in another client.
        $key = (string) config('app.key').'|'.self::secret().'|'.hash('sha256', (string) $request->userAgent());

        return hash_hmac('sha256', $payload, $key);
    }

    /** Stable id of the currently opened gate (used to count failed sign-ins per gate). */
    public static function fingerprint(Request $request): ?string
    {
        $value = $request->cookie(self::SECURE) ?: $request->cookie(self::PLAIN);

        return is_string($value) && $value !== '' ? hash('sha256', $value) : null;
    }

    public static function isOpen(Request $request): bool
    {
        if (self::secret() === '') {
            return false;
        }

        $value = $request->cookie(self::SECURE) ?: $request->cookie(self::PLAIN);
        if (! is_string($value) || substr_count($value, '.') !== 1) {
            return false;
        }

        [$expires, $signature] = explode('.', $value);

        return ctype_digit($expires)
            && (int) $expires > time()
            && hash_equals(self::sign($expires, $request), $signature);
    }

    public static function open(Request $request): Cookie
    {
        $expires = time() + self::minutes() * 60;

        return self::make($request, $expires.'.'.self::sign((string) $expires, $request), $expires);
    }

    public static function close(Request $request): Cookie
    {
        return self::make($request, '', time() - 86400);
    }

    private static function make(Request $request, string $value, int $expires): Cookie
    {
        return new Cookie(
            self::cookieName($request),
            $value,
            $expires,
            '/',
            null,              // host-only (required by __Host-)
            $request->isSecure(),
            true,              // HttpOnly
            false,
            Cookie::SAMESITE_STRICT
        );
    }
}
