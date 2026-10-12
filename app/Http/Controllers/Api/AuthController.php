<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CookieAuth;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\LoginGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    private const TOKEN_HOURS = 12;          // normal session
    private const REMEMBER_HOURS = 24 * 7;   // "keep me signed in"

    /** POST /auth/gate  { secret } — opens the login for a short time (HttpOnly cookie). Wrong secret => plain 404. */
    public function openGate(Request $request): Response
    {
        if (! LoginGate::secretMatches($request->input('secret'))) {
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
        // Without the secret-link cookie the login endpoint pretends not to exist.
        abort_unless(LoginGate::isOpen($request), 404);

        // After 5 wrong passwords the opened gate is burned: the secret link is needed again.
        $failKey = 'login-gate-fail:'.LoginGate::fingerprint($request);
        if ((int) Cache::get($failKey, 0) >= LoginGate::MAX_LOGIN_FAILURES) {
            return response()->json(['message' => 'Not Found.'], 404)->withCookie(LoginGate::close($request));
        }

        $user = User::where('email', $request->validated('email'))->first();

        // One generic message for "no such user", "not an admin" and "wrong password".
        if (! $user || ! $user->is_admin || ! Hash::check($request->validated('password'), $user->password)) {
            Cache::add($failKey, 0, now()->addMinutes(LoginGate::minutes() + 1));
            Cache::increment($failKey);

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $remember = (bool) $request->validated('remember', false);
        $expires = now()->addHours($remember ? self::REMEMBER_HOURS : self::TOKEN_HOURS);

        // One active session: signing in revokes every earlier token (also kills any leaked/old one).
        $user->tokens()->delete();
        $token = $user->createToken('admin-panel', ['admin'], $expires);

        // The token is NOT returned in the body: it only travels in an HttpOnly cookie.
        return response()
            ->json(['data' => [
                'expiresAt' => $expires->toIso8601String(),
                'user' => $this->payload($user),
            ]])
            ->withCookie($this->cookie($request, $token->plainTextToken, $remember ? $expires : 0))
            ->withCookie(LoginGate::close($request)); // the gate is single-use: next sign-in needs the link again
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
