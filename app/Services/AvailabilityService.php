<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Provider;
use App\Models\ProviderAvailability;
use Carbon\Carbon;

class AvailabilityService
{
    public function isSlotAvailable(Provider $provider, Carbon $start, ?int $durationMinutes = null): bool
    {
        $durationMinutes ??= 60;
        $end = $start->copy()->addMinutes($durationMinutes);

        $dayName = strtolower($start->englishDayOfWeek);

        $hasSchedule = ProviderAvailability::query()
            ->where('provider_id', $provider->id)
            ->where('is_available', true)
            ->where('day_of_week', $dayName)
            ->whereTime('start_time', '<=', $start->format('H:i:s'))
            ->whereTime('end_time', '>=', $end->format('H:i:s'))
            ->exists();

        if (! $hasSchedule) {
            return false;
        }

        return ! $this->hasOverlappingBooking($provider, $start, $end);
    }

    protected function hasOverlappingBooking(Provider $provider, Carbon $start, Carbon $end): bool
    {
        $driver = Booking::query()->getConnection()->getDriverName();

        $query = Booking::query()
            ->where('provider_id', $provider->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('booking_date', '<', $end);

        if ($driver === 'mysql') {
            $query->whereRaw(
                'DATE_ADD(booking_date, INTERVAL 60 MINUTE) > ?',
                [$start->format('Y-m-d H:i:s')]
            );
        } else {
            $query->whereRaw(
                "datetime(booking_date, '+60 minutes') > ?",
                [$start->format('Y-m-d H:i:s')]
            );
        }

        return $query->exists();
    }
}
