<?php

use App\Http\Middleware\CookieAuth;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Railway/Vercel sit in front of the app: trust their X-Forwarded-* headers (real client IP, https detection).
        $middleware->trustProxies(at: '*');
        $middleware->api(prepend: [CookieAuth::class]);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Always answer API calls with JSON (401/403/404/422 ...), never an HTML page.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })
    ->create();
