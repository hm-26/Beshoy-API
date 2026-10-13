<?php

use App\Http\Middleware\CookieAuth;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\OpenSealed;
use App\Http\Middleware\SealResponse;
use App\Http\Middleware\RequireLoginGate;
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
        // Railway/Vercel sit in front of the app (Vercel -> Railway), so the real client IP only exists in X-Forwarded-For.
        // '*' (the default) trusts every hop, which keeps the real-IP detection working behind that chain, BUT it also means a
        // client that talks to the Railway URL directly can forge X-Forwarded-For and dodge the per-IP rate limits.
        // That is low impact here (the login still needs the 48-char gate secret, a per-gate failure cap and a global cap),
        // and it is closed completely by setting TRUSTED_PROXIES in the environment to the real proxy IPs/CIDRs
        // (comma separated). Only set it once you have confirmed those addresses — a wrong value makes every visitor
        // share one IP and can break the login throttle.
        $trusted = trim((string) env('TRUSTED_PROXIES', '*'));
        $middleware->trustProxies(at: $trusted === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $trusted)))));
        $middleware->api(prepend: [CookieAuth::class]);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'login.gate' => RequireLoginGate::class,
            'sealed' => OpenSealed::class,
            'seal' => SealResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Always answer API calls with JSON (401/403/404/422 ...), never an HTML page.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })
    ->create();
