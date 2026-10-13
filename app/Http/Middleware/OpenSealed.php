<?php

namespace App\Http\Middleware;

use App\Support\SealedPayload;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replaces the request body with the decrypted content of its `sealed` envelope (see SealedPayload), so the rest of
 * the stack — throttling, validation, controllers — sees the normal fields. With REQUIRE_SEALED_LOGIN=true (default)
 * a readable body is refused.
 */
class OpenSealed
{
    public function handle(Request $request, Closure $next): Response
    {
        $envelope = $request->input('sealed');

        if ($envelope === null) {
            if (config('auth.require_sealed', true)) {
                return $this->refuse();
            }

            return $next($request);
        }

        $payload = SealedPayload::open($envelope);
        if ($payload === null) {
            return $this->refuse();
        }

        $request->replace($payload);

        return $next($request);
    }

    private function refuse(): Response
    {
        return response()->json([
            'message' => 'The request could not be opened. Reload the page and try again.',
            'code' => 'sealed_invalid',
        ], 400);
    }
}
