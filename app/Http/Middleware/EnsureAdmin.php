<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // The user must be flagged as admin AND the token must carry the "admin" ability.
        if (! $user || ! $user->is_admin || ! $user->tokenCan('admin')) {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}
