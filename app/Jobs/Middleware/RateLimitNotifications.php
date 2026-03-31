<?php

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitNotifications
{
    /**
     * Throttle notification-style jobs to avoid bursts against SMTP or FCM.
     *
     * @param  \Closure(object): void  $next
     */
    public function handle(object $job, Closure $next): void
    {
        $key = 'notifications:'.get_class($job);

        if (RateLimiter::tooManyAttempts($key, 60)) {
            $job->release(30);

            return;
        }

        RateLimiter::hit($key, decaySeconds: 60);

        $next($job);
    }
}
