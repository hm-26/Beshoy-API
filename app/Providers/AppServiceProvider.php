<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Login: 6 tries / minute per IP, and 30 tries / hour per e-mail+IP.
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(6)->by('ip:'.$request->ip()),
                Limit::perHour(30)->by('email:'.$email.'|'.$request->ip()),
            ];
        });

        // Secret-link check. Only per-IP on purpose: a global cap would let any stranger lock the admin out, and
        // brute-forcing a 48-char secret is hopeless anyway.
        RateLimiter::for('gate', function (Request $request) {
            return [
                Limit::perMinute(5)->by('gate-ip:'.$request->ip()),
                Limit::perHour(20)->by('gate-ip-h:'.$request->ip()),
            ];
        });
    }
}
