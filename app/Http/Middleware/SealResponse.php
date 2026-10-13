<?php

namespace App\Http\Middleware;

use App\Support\SealedPayload;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Encrypts successful JSON responses for the browser that asked (see SealedPayload::sealReply), so the data is not
 * readable in an intercepting proxy (Burp, a CDN, a corporate TLS proxy) or in the network log: only ciphertext crosses
 * the wire. The browser sends X-Seal-Key + X-Seal-Nonce.
 *
 *   seal:required  -> a request without them is refused (public API: nothing readable for curl / scanners)
 *   seal           -> sealed when they are sent, plain otherwise (admin API: file uploads cannot send them)
 */
class SealResponse
{
    public function handle(Request $request, Closure $next, string $mode = 'optional'): Response
    {
        $key = $request->header('X-Seal-Key');
        $nonce = $request->header('X-Seal-Nonce');

        if (! is_string($key) || ! is_string($nonce)) {
            if ($mode === 'required' && config('auth.seal_public_api', true)) {
                return response()->json(['message' => 'Sealed request required.', 'code' => 'sealed_required'], 400);
            }

            return $next($request);
        }

        $response = $next($request);

        // Only successful JSON bodies are sealed; errors carry no data and stay readable for the front-end.
        if ($response->getStatusCode() !== 200 || ! str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            return $response;
        }

        $sealed = SealedPayload::sealReply((string) $response->getContent(), $key, $nonce);
        if ($sealed === null) {
            return response()->json(['message' => 'Invalid seal key.', 'code' => 'sealed_invalid'], 400);
        }

        $response->setContent(json_encode(['sealed' => $sealed]));
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Content-Length', (string) strlen((string) $response->getContent()));

        return $response;
    }
}
