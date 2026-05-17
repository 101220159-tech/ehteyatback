<?php

namespace App\Http\Controllers\Concerns;

use App\Support\SafeBroadcast;
use Illuminate\Http\Request;

trait BroadcastsChatEventWithSocket
{
    /**
     * Broadcast immediately in the same HTTP request (with ShouldBroadcastNow on events) so
     * delivery is not delayed by the queue. Sets `X-Socket-ID` on the event so Reverb/Pusher
     * skips the sender's connection (avoids duplicate Echo bubbles).
     */
    protected function broadcastChatEventWithSocket(Request $request, callable $eventFactory): void
    {
        $event = $eventFactory();
        $socketId = $request->header('X-Socket-ID');
        if (is_string($socketId) && $socketId !== '') {
            $event->socket = $socketId;
        }
        SafeBroadcast::send($event);
    }
}
