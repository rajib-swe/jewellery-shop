<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Named rate limiters, so the limits that guard money live in one place
     * instead of as `throttle:5,1` strings scattered over the routes.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip().'|'.$request->input('email'));
        });

        // A successful sign-in resets the login allowance, so a cashier who
        // fumbled their password four times is not then locked out of a clean
        // fifth attempt.
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(180)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        // Dumping the whole database is expensive and writes to disk, so the
        // manual button is rate limited separately from ordinary reads.
        RateLimiter::for('backups', function (Request $request): Limit {
            return Limit::perMinute(6)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });
    }
}
