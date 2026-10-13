<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminPasswordController extends Controller
{
    private const MAX_FAILURES = 5;      // wrong current password / wrong code, per hour
    private const MIN_CODE_LENGTH = 6;

    /**
     * PUT /admin/password  { currentPassword, newPassword, newPasswordConfirmation, code }
     *
     * Needs three things: a signed-in admin, the current password, and the verification code that lives in the
     * server environment (ADMIN_PASSWORD_CODE) — never in the source code or the database.
     */
    public function update(Request $request): JsonResponse
    {
        $expected = (string) config('auth.password_change_code', '');
        if (strlen($expected) < self::MIN_CODE_LENGTH) {
            return response()->json([
                'message' => 'Changing the password from the site is disabled: set ADMIN_PASSWORD_CODE on the server first.',
                'code' => 'not_configured',
            ], 503);
        }

        $user = $request->user();
        $limiterKey = 'admin-password-change:'.$user->id;
        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_FAILURES)) {
            $minutes = (int) ceil(RateLimiter::availableIn($limiterKey) / 60);

            return response()->json(['message' => "Too many wrong attempts. Try again in {$minutes} minute(s)."], 429);
        }

        $data = $request->validate([
            'currentPassword' => ['required', 'string', 'max:255'],
            'newPassword' => ['required', 'string', 'max:255', Password::min(10), 'different:currentPassword'],
            'newPasswordConfirmation' => ['required', 'same:newPassword'],
            'code' => ['required', 'string', 'max:255'],
        ]);

        // Compare hashes, so the comparison time does not depend on how many characters of the code are right.
        $codeOk = hash_equals(hash('sha256', $expected), hash('sha256', $data['code']));
        $passwordOk = Hash::check($data['currentPassword'], $user->password);

        if (! $codeOk || ! $passwordOk) {
            RateLimiter::hit($limiterKey, 3600);

            // One message for both, so the form never says which of the two was wrong.
            throw ValidationException::withMessages([
                'code' => ['The current password or the verification code is wrong.'],
            ]);
        }

        RateLimiter::clear($limiterKey);

        $user->forceFill(['password' => $data['newPassword']])->save(); // hashed by the User model cast

        // A new password means every OTHER session must sign in again; this one stays open.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['data' => ['changed' => true]]);
    }
}
