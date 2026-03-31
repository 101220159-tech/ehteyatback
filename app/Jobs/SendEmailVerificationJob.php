<?php

namespace App\Jobs;

use App\Jobs\Middleware\RateLimitNotifications;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmailVerificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $userId) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimitNotifications];
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        if ($user && ! $user->hasVerifiedEmail()) {
            Mail::to($user->email)->queue(new VerifyEmailMail($user));
        }
    }
}
