<?php

namespace App\Http\Middleware;

use App\Support\LoginGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs BEFORE validation and rate limiting: without the secret-link cookie the login endpoint answers a plain 404,
 * exactly like a route that does not exist (no 422 that would reveal it, no limiter quota burned by strangers).
 */
class RequireLoginGate
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(LoginGate::isOpen($request), 404);

        return $next($request);
    }
}
