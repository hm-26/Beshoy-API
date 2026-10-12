<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Unique-visitor counter.
 *
 * A visitor is a browser: the first visit hands out a random id in an HttpOnly cookie (1 year); the database keeps only a
 * keyed hash of it, so nothing personal is stored and the same browser is never counted twice.
 */
class VisitController extends Controller
{
    private const PLAIN = 'visitor';
    private const SECURE = '__Host-visitor';
    private const NEW_VISITORS_PER_IP_PER_HOUR = 10; // cookie-less bots cannot inflate the number quickly

    /** GET /visits — the current total only. */
    public function show(): JsonResponse
    {
        return $this->respond();
    }

    /** POST /visits — counts this browser (once) and returns the total. */
    public function hit(Request $request): JsonResponse
    {
        $name = $request->isSecure() ? self::SECURE : self::PLAIN;
        $id = $request->cookie($name);
        $cookie = null;

        if (! is_string($id) || ! preg_match('/^[a-f0-9]{32}$/', $id)) {
            $id = null;
            // Only browsers without a valid cookie can create a new visitor, and only a few per IP per hour.
            if (RateLimiter::attempt('visit-new:'.$request->ip(), self::NEW_VISITORS_PER_IP_PER_HOUR, fn () => true, 3600)) {
                $id = bin2hex(random_bytes(16));
                $cookie = new Cookie(
                    $name, $id, time() + 365 * 86400, '/', null,
                    $request->isSecure(), true, false, Cookie::SAMESITE_LAX
                );
            }
        }

        if ($id !== null) {
            $inserted = DB::table('visitors')->insertOrIgnore([
                'token_hash' => hash_hmac('sha256', $id, (string) config('app.key')),
                'created_at' => now(),
            ]);
            if ($inserted) {
                Cache::forget('visitors-count');
            }
        }

        return $this->respond($cookie);
    }

    private function respond(?Cookie $cookie = null): JsonResponse
    {
        $count = (int) Cache::remember('visitors-count', 30, fn () => DB::table('visitors')->count());

        $response = response()->json(['data' => ['count' => $count]])->header('Cache-Control', 'no-store, private');

        return $cookie ? $response->withCookie($cookie) : $response;
    }
}
