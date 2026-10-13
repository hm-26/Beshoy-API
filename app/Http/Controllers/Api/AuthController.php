<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CookieAuth;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\AdminSessions;
use App\Support\LoginGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    private const TOKEN_HOURS = 12;          // normal session
    private const REMEMBER_HOURS = 24 * 7;   // "keep me signed in"

    // Long-lived HttpOnly cookie that tells the API "this is the same browser as before", so you are never locked out
    // by your own earlier session (e.g. you closed the tab and sign in again from the same laptop).
    private const DEVICE_PLAIN = 'admin_device';
    private const DEVICE_SECURE = '__Host-admin_device';

    /** POST /auth/gate  { secret } — opens the login for a short time (HttpOnly cookie). Wrong secret => plain 404. */
    public function openGate(Request $request): Response
    {
        // Non-string input (e.g. secret[]=x) must behave exactly like a wrong secret, not crash with a 500.
        $secret = $request->input('secret');
        if (! is_string($secret) || ! LoginGate::secretMatches($secret)) {
            abort(404);
        }

        return response()->noContent()->withCookie(LoginGate::open($request));
    }

    /** GET /auth/gate — 204 while the login cookie is valid, otherwise 404. */
    public function gateStatus(Request $request): Response
    {
        abort_unless(LoginGate::isOpen($request), 404);

        return response()->noContent();
    }

    public function login(LoginRequest $request): JsonResponse
    {
        // (The `login.gate` middleware already answered 404 for requests without an open gate.)
        abort_unless(LoginGate::isOpen($request), 404);

        // Total wrong passwords per hour across all gates. Only someone who already has the secret link can reach
        // this, so it cannot be used by strangers to lock the admin out.
        if (RateLimiter::tooManyAttempts('login-fail-global', 20)) {
            return response()->json(['message' => 'Too many attempts. Try again later.'], 429);
        }

        // After 5 wrong passwords the opened gate is burned: the secret link is needed again.
        $failKey = 'login-gate-fail:'.LoginGate::fingerprint($request);
        if ((int) Cache::get($failKey, 0) >= LoginGate::MAX_LOGIN_FAILURES) {
            return response()->json(['message' => 'Not Found.'], 404)->withCookie(LoginGate::close($request));
        }

        $user = User::where('email', $request->validated('email'))->first();

        // Always run one bcrypt comparison, so the response time does not reveal whether the e-mail exists.
        $hash = $user?->password ?? '$2y$12$pZU/m5nRxT1dMmz0TMHYBOZzT7hpIgXpvOKteLOGQ/AmYVw8HvJqO';
        $passwordOk = Hash::check($request->validated('password'), $hash);

        // One generic message for "no such user", "not an admin" and "wrong password".
        if (! $user || ! $user->is_admin || ! $passwordOk) {
            RateLimiter::hit('login-fail-global', 3600);
            Cache::add($failKey, 0, now()->addMinutes(LoginGate::minutes() + 1));
            Cache::increment($failKey);

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $remember = (bool) $request->validated('remember', false);
        $expires = now()->addHours($remember ? self::REMEMBER_HOURS : self::TOKEN_HOURS);

        // The admin chooses (Admin > Settings) how many devices may be signed in at once; the default is 1.
        // Devices that are already signed in keep their place: a new one has to wait until one of them signs out
        // (or goes idle). The password was checked first, so this is never shown to strangers.
        $device = $this->deviceId($request);
        $tokenName = 'admin-panel:'.hash('sha256', $device);

        $others = AdminSessions::active($user)->where('name', '!=', $tokenName)->pluck('id');
        if ($others->count() >= AdminSessions::limit($user)) {
            return response()->json([
                'message' => 'The maximum number of signed-in devices is reached. Sign out on another device first, then try again.',
                'code' => 'session_active',
            ], 409);
        }

        // Keep the other devices that are active; drop everything else (this browser's old token, expired and idle ones).
        $user->tokens()->whereNotIn('id', $others)->delete();
        $token = $user->createToken($tokenName, ['admin'], $expires);

        // The token is NOT returned in the body: it only travels in an HttpOnly cookie.
        return response()
            ->json(['data' => [
                'expiresAt' => $expires->toIso8601String(),
                'user' => $this->payload($user),
            ]])
            ->withCookie($this->cookie($request, $token->plainTextToken, $remember ? $expires : 0))
            ->withCookie($this->deviceCookie($request, $device))
            ->withCookie(LoginGate::close($request)); // the gate is single-use: next sign-in needs the link again
    }

    private function deviceId(Request $request): string
    {
        $name = $request->isSecure() ? self::DEVICE_SECURE : self::DEVICE_PLAIN;
        $value = $request->cookie($name);

        return is_string($value) && preg_match('/^[A-Za-z0-9]{40}$/', $value) ? $value : Str::random(40);
    }

    private function deviceCookie(Request $request, string $value): Cookie
    {
        return new Cookie(
            $request->isSecure() ? self::DEVICE_SECURE : self::DEVICE_PLAIN,
            $value,
            now()->addYear(),
            '/',
            null,          // host-only: required by the __Host- prefix
            $request->isSecure(),
            true,          // HttpOnly
            false,
            Cookie::SAMESITE_STRICT
        );
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($request->user())]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent()
            ->withCookie($this->cookie($request, '', now()->subDay()))
            ->withCookie(LoginGate::close($request));
    }

    private function cookie(Request $request, string $value, $expires): Cookie
    {
        return new Cookie(
            CookieAuth::cookieName($request),
            $value,
            $expires,      // 0 => session cookie (gone when the browser closes)
            '/',
            null,          // host-only: required by the __Host- prefix
            $request->isSecure(),
            true,          // HttpOnly: invisible to JavaScript
            false,
            Cookie::SAMESITE_STRICT
        );
    }

    private function payload(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'isAdmin' => (bool) $user->is_admin];
    }
}
