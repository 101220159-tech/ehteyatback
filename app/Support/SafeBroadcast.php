<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Broadcast without failing the HTTP request when Reverb/Pusher is down.
 */
final class SafeBroadcast
{
    public static function send(object $event): void
    {
        if (config('broadcasting.default') === 'null') {
            return;
        }

        try {
            broadcast($event);
        } catch (Throwable $e) {
            Log::warning('Broadcast skipped (Reverb unavailable?).', [
                'event' => $event::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
