<?php

namespace App\Services;

use App\Events\ProviderScheduleUpdated;
use App\Models\Booking;
use App\Models\ProviderSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProviderScheduleService
{
    /**
     * @return array{starts_at: Carbon, ends_at: Carbon}
     */
    public function windowFromBooking(Booking $booking): array
    {
        $startsAt = Carbon::parse($booking->scheduled_at);
        $endsAt   = $startsAt->copy()->addMinutes((int) ($booking->duration_minutes ?? 60));

        return compact('startsAt', 'endsAt');
    }

    public function hasConflict(
        string $providerId,
        Carbon $startsAt,
        int $durationMinutes,
        ?string $excludeBookingId = null
    ): bool {
        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

        $bookingConflict = Booking::query()
            ->where('provider_id', $providerId)
            ->where('status', 'accepted')
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->where(function ($q) use ($startsAt, $endsAt) {
                $q->whereBetween('scheduled_at', [$startsAt, $endsAt]);
                if (DB::connection()->getDriverName() === 'sqlite') {
                    $q->orWhereRaw(
                        "datetime(scheduled_at, '+' || duration_minutes || ' minutes') BETWEEN ? AND ?",
                        [$startsAt->format('Y-m-d H:i:s'), $endsAt->format('Y-m-d H:i:s')]
                    );
                } else {
                    $q->orWhereRaw(
                        'DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) BETWEEN ? AND ?',
                        [$startsAt, $endsAt]
                    );
                }
            })
            ->exists();

        if ($bookingConflict) {
            return true;
        }

        return ProviderSchedule::query()
            ->where('provider_id', $providerId)
            ->whereIn('status', ['pending', 'accepted'])
            ->when($excludeBookingId, fn ($q) => $q->where('booking_id', '!=', $excludeBookingId))
            ->where(function ($q) use ($startsAt, $endsAt) {
                $driver = DB::connection()->getDriverName();
                if ($driver === 'sqlite') {
                    $q->whereRaw(
                        "datetime(scheduled_date || ' ' || scheduled_time) < ? AND datetime(scheduled_date || ' ' || scheduled_time, '+' || duration_minutes || ' minutes') > ?",
                        [$endsAt->format('Y-m-d H:i:s'), $startsAt->format('Y-m-d H:i:s')]
                    );
                } else {
                    $q->whereRaw(
                        'TIMESTAMP(scheduled_date, scheduled_time) < ? AND DATE_ADD(TIMESTAMP(scheduled_date, scheduled_time), INTERVAL duration_minutes MINUTE) > ?',
                        [$endsAt, $startsAt]
                    );
                }
            })
            ->exists();
    }

    public function assertNoConflict(
        string $providerId,
        Carbon $startsAt,
        int $durationMinutes,
        ?string $excludeBookingId = null
    ): void {
        if ($this->hasConflict($providerId, $startsAt, $durationMinutes, $excludeBookingId)) {
            throw ValidationException::withMessages([
                'scheduled_at' => ['This time slot conflicts with an existing booking.'],
            ]);
        }
    }

    public function createFromBooking(Booking $booking): ProviderSchedule
    {
        $startsAt = Carbon::parse($booking->scheduled_at);

        return ProviderSchedule::create([
            'provider_id'       => $booking->provider_id,
            'booking_id'      => $booking->id,
            'scheduled_date'    => $startsAt->toDateString(),
            'scheduled_time'    => $startsAt->format('H:i:s'),
            'duration_minutes'  => (int) ($booking->duration_minutes ?? 60),
            'status'            => $this->mapBookingStatusToSchedule($booking->status),
        ]);
    }

    public function syncFromBooking(Booking $booking): ProviderSchedule
    {
        $startsAt = Carbon::parse($booking->scheduled_at);
        $status   = $this->mapBookingStatusToSchedule($booking->status);

        $schedule = ProviderSchedule::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'provider_id'      => $booking->provider_id,
                'scheduled_date'   => $startsAt->toDateString(),
                'scheduled_time'   => $startsAt->format('H:i:s'),
                'duration_minutes' => (int) ($booking->duration_minutes ?? 60),
                'status'           => $status,
            ]
        );

        event(new ProviderScheduleUpdated($schedule->load(['booking.customer', 'booking.service'])));

        return $schedule;
    }

    public function mapBookingStatusToSchedule(string $bookingStatus): string
    {
        return match ($bookingStatus) {
            'pending'               => 'pending',
            'accepted'              => 'accepted',
            'completed'             => 'completed',
            'cancelled', 'rejected' => 'cancelled',
            default                 => 'pending',
        };
    }

    /**
     * @return list<string> Time strings (H:i) occupied on a given date.
     */
    public function occupiedTimeSlots(string $providerId, string $date): array
    {
        $schedules = ProviderSchedule::query()
            ->where('provider_id', $providerId)
            ->where('scheduled_date', $date)
            ->whereIn('status', ['pending', 'accepted'])
            ->get(['scheduled_time', 'duration_minutes']);

        $slots = [];
        foreach ($schedules as $schedule) {
            $start = Carbon::parse($date.' '.$schedule->scheduled_time);
            $end   = $start->copy()->addMinutes($schedule->duration_minutes);
            $cursor = $start->copy();
            while ($cursor < $end) {
                $slots[] = $cursor->format('H:i');
                $cursor->addMinutes(30);
            }
        }

        return array_values(array_unique($slots));
    }
}
