<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Provider;
use Illuminate\Console\Command;

/**
 * Runs daily (scheduled in routes/console.php).
 *
 * - Marks payments as "expired" when their expires_at has passed.
 * - Deactivates providers whose subscription has expired.
 */
class DeactivateExpiredSubscriptions extends Command
{
    protected $signature   = 'subscriptions:deactivate-expired';
    protected $description = 'Deactivate providers whose subscription has expired.';

    public function handle(): int
    {
        $now = now();

        // Mark expired payments
        Payment::where('status', 'completed')
            ->where('expires_at', '<', $now)
            ->update(['status' => 'expired']);

        // Deactivate providers whose subscription_expires_at has passed
        $count = Provider::where('is_active', true)
            ->whereNotNull('subscription_expires_at')
            ->where('subscription_expires_at', '<', $now)
            ->update(['is_active' => false]);

        $this->info("Deactivated {$count} provider(s) with expired subscriptions.");

        return self::SUCCESS;
    }
}
