<?php

namespace App\Services;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function sendInApp(User $user, string $type, string $title, string $message, ?array $data = null): Notification
    {
        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        SendNotificationJob::dispatch($notification->id, ['in_app']);

        return $notification;
    }

    public function sendEmail(User $user, string $subject, string $view, array $viewData = []): void
    {
        Mail::send($view, $viewData, function ($message) use ($user, $subject) {
            $message->to($user->email)->subject($subject);
        });
    }
}
