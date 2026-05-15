<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $paths = [];
        $configured = config('services.http.ca_bundle');
        if (is_string($configured) && $configured !== '') {
            $paths[] = $configured;
        }
        $paths[] = base_path('storage/certs/cacert.pem');

        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                $resolved = realpath($path) ?: $path;
                Http::globalOptions(['verify' => $resolved]);
                break;
            }
        }

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
