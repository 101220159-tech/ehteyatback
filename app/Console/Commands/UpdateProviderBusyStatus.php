<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Provider;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Runs every minute (scheduled in routes/console.php).
 *
 * - Sets provider.is_busy = true  when an accepted booking's scheduled_at is now.
 * - Sets provider.is_busy = false when no accepted booking is active for them.
 */
class UpdateProviderBusyStatus extends Command
{
    protected $signature   = 'providers:update-busy-status';
    protected $description = 'Mark providers as busy/free based on active bookings.';

    public function handle(): int
    {
        $now = Carbon::now();

        // Mark BUSY — accepted bookings whose window covers right now
        $activeBookings = Booking::where('status', 'accepted')
            ->where('scheduled_at', '<=', $now)
            ->whereRaw('DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) >= ?', [$now])
            ->with('provider')
            ->get();

        $busyProviderIds = $activeBookings->pluck('provider_id')->unique()->all();

        if ($busyProviderIds) {
            Provider::whereIn('id', $busyProviderIds)->update(['is_busy' => true]);
        }

        // Mark FREE — providers who are marked busy but have no active booking right now
        Provider::where('is_busy', true)
            ->whereNotIn('id', $busyProviderIds)
            ->update(['is_busy' => false]);

        $this->info("Busy: ".count($busyProviderIds)." provider(s) updated.");

        return self::SUCCESS;
    }
}
