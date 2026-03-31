<?php

namespace App\Jobs;

use App\Models\Provider;
use App\Services\RatingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateProviderRatingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $providerId) {}

    public function handle(RatingService $ratings): void
    {
        $provider = Provider::query()->find($this->providerId);
        if ($provider) {
            $ratings->recalculate($provider);
        }
    }
}
