<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const TOKEN_HOURS = 12;

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        // One generic message for "no such user", "not an admin" and "wrong password".
        if (! $user || ! $user->is_admin || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $user->tokens()->where('expires_at', '<', now())->delete(); // tidy up expired tokens

        $expires = now()->addHours(self::TOKEN_HOURS);
        $token = $user->createToken('admin-panel', ['admin'], $expires);

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'expiresAt' => $expires->toIso8601String(),
            'user' => $this->payload($user),
        ]]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($request->user())]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    private function payload(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'isAdmin' => (bool) $user->is_admin];
    }
}
