<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CookieAuth;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    private const TOKEN_HOURS = 12;          // normal session
    private const REMEMBER_HOURS = 24 * 7;   // "keep me signed in"

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        // One generic message for "no such user", "not an admin" and "wrong password".
        if (! $user || ! $user->is_admin || ! Hash::check($request->validated('password'), $user->password)) {
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
            ->withCookie($this->cookie($request, $token->plainTextToken, $remember ? $expires : null));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($request->user())]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent()->withCookie($this->cookie($request, '', now()->subDay()));
    }

    private function cookie(Request $request, string $value, $expires): Cookie
    {
        return new Cookie(
            CookieAuth::cookieName($request),
            $value,
            $expires,      // null => session cookie (gone when the browser closes)
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
