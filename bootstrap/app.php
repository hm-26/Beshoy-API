<?php

use App\Http\Middleware\CookieAuth;
use App\Http\Middleware\EnsureAdmin;
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
        // Railway/Vercel sit in front of the app: trust X-Forwarded-* ONLY when the request really comes from them.
        // Trusting '*' would let any client forge X-Forwarded-For and dodge the per-IP login/gate rate limits.
        // Default = private/internal ranges (where platform proxies connect from). Override with TRUSTED_PROXIES
        // (comma separated IPs/CIDRs) if your host's proxy uses other addresses.
        $middleware->trustProxies(at: array_values(array_filter(array_map('trim', explode(',', (string) env(
            'TRUSTED_PROXIES',
            '127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,100.64.0.0/10,fc00::/7'
        ))))));
        $middleware->api(prepend: [CookieAuth::class]);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'login.gate' => RequireLoginGate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Always answer API calls with JSON (401/403/404/422 ...), never an HTML page.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })
    ->create();
