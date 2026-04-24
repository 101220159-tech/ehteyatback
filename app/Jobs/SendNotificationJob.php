<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotificationJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  list<string>  $channels
     */
    public function __construct(
        public string $notificationId,
        public array $channels = ['in_app']
    ) {}

    public function handle(): void
    {
        $notification = Notification::query()->find($this->notificationId);
        if (! $notification) {
            return;
        }

        if (in_array('email', $this->channels, true)) {
            $user = User::query()->find($notification->user_id);
            if ($user) {
                Mail::raw($notification->body ?? '', function ($message) use ($user, $notification) {
                    $message->to($user->email)->subject($notification->title ?? '');
                });
            }
        }

        if (in_array('push', $this->channels, true)) {
            Log::info('push.notification', ['id' => $this->notificationId]);
        }
    }
}
