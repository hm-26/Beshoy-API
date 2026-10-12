<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * "Secret login link" gate.
 *
 * The admin login is only usable after the visitor opened  /0xGlitch#secret=<ADMIN_LOGIN_SECRET>.
 * The front-end sends that secret once to POST /api/auth/gate; if it matches, the API creates a server-side gate
 * (random id stored in the cache, so it can be revoked at any time) and hands the browser a signed
 * HttpOnly + SameSite=Strict cookie that points to it. Every login request needs that cookie.
 *
 * Cookie value:  <id>.<expires>.<hmac(id.expires, APP_KEY | secret | User-Agent)>
 */
class LoginGate
{
    public const PLAIN = 'login_gate';
    public const SECURE = '__Host-login_gate';
    public const MIN_SECRET_LENGTH = 32;
    public const MAX_LOGIN_FAILURES = 5;   // wrong passwords allowed per opened gate

    /** A secret shorter than 32 chars is treated as "not configured" => login stays locked. */
    public static function secret(): string
    {
        $secret = (string) config('auth.login_secret', '');

        return strlen($secret) >= self::MIN_SECRET_LENGTH ? $secret : '';
    }

    public static function minutes(): int
    {
        return max(1, min(60, (int) config('auth.login_gate_minutes', 10)));
    }

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

    /**
     * Over HTTPS only the __Host- cookie is accepted. A plain-named cookie can be planted from a sibling
     * sub-domain ("cookie tossing"); the __Host- prefix makes that impossible.
     */
    private static function rawCookie(Request $request): ?string
    {
        $value = $request->isSecure() ? $request->cookie(self::SECURE) : $request->cookie(self::PLAIN);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function sign(string $payload, Request $request): string
    {
        $key = (string) config('app.key').'|'.self::secret().'|'.hash('sha256', (string) $request->userAgent());

        return hash_hmac('sha256', $payload, $key);
    }

    /** @return array{0:string,1:string}|null [id, expires] of a correctly signed, unexpired cookie */
    private static function parse(Request $request): ?array
    {
        if (self::secret() === '' || ! ($value = self::rawCookie($request))) {
            return null;
        }
        $parts = explode('.', $value);
        if (count($parts) !== 3) {
            return null;
        }
        [$id, $expires, $signature] = $parts;

        if (! preg_match('/^[a-f0-9]{32}$/', $id) || ! ctype_digit($expires) || (int) $expires <= time()) {
            return null;
        }

        return hash_equals(self::sign($id.'.'.$expires, $request), $signature) ? [$id, $expires] : null;
    }

    private static function cacheKey(string $id): string
    {
        return 'login-gate:'.$id;
    }

    /** Id of the currently opened gate (used to count failed sign-ins per gate), or null. */
    public static function fingerprint(Request $request): ?string
    {
        return self::parse($request)[0] ?? null;
    }

    public static function isOpen(Request $request): bool
    {
        $parsed = self::parse($request);

        // Must also still exist server-side: closing a gate revokes it even if the cookie was copied.
        return $parsed !== null && Cache::has(self::cacheKey($parsed[0]));
    }

    public static function open(Request $request): Cookie
    {
        $id = bin2hex(random_bytes(16));
        $expires = time() + self::minutes() * 60;
        Cache::put(self::cacheKey($id), 1, $expires - time());

        return self::make($request, $id.'.'.$expires.'.'.self::sign($id.'.'.$expires, $request), $expires);
    }

    /** Revoke the gate on the server and clear the cookie. */
    public static function close(Request $request): Cookie
    {
        if ($id = self::fingerprint($request)) {
            Cache::forget(self::cacheKey($id));
        }

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
