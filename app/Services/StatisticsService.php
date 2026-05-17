<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DeliveryBooking;
use App\Models\Provider;
use App\Models\Service;
use App\Models\TaxiBooking;
use App\Models\User;
use Carbon\Carbon;

class StatisticsService
{
    /**
     * @return array<string, mixed>
     */
    public function adminLegacyStats(): array
    {
        return [
            'users_count' => User::query()->count(),
            'providers_count' => Provider::query()->count(),
            'services_count' => Service::query()->count(),
            'bookings_pending' => Booking::query()->where('status', 'pending')->count(),
            'completed_bookings_count' => Booking::query()->where('status', 'completed')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adminOverview(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->startOfMonth();
        $to ??= now()->endOfDay();

        $bookingsInPeriod = Booking::query()->whereBetween('created_at', [$from, $to]);

        return [
            'period' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'users' => [
                'total' => User::query()->count(),
                'customers' => User::query()->whereHas('role', fn ($q) => $q->where('name', 'customer'))->count(),
                'providers' => Provider::query()->count(),
                'active_providers' => Provider::query()->where('is_active', true)->count(),
            ],
            'services' => [
                'total' => Service::query()->count(),
            ],
            'bookings' => [
                'total_in_period' => (clone $bookingsInPeriod)->count(),
                'pending' => Booking::query()->where('status', 'pending')->count(),
                'accepted' => Booking::query()->where('status', 'accepted')->count(),
                'completed' => Booking::query()->where('status', 'completed')->count(),
                'cancelled' => Booking::query()->where('status', 'cancelled')->count(),
                'completed_in_period' => (clone $bookingsInPeriod)->where('status', 'completed')->count(),
                'revenue_completed_in_period' => (float) (clone $bookingsInPeriod)
                    ->where('status', 'completed')
                    ->sum('price'),
            ],
            'transport' => $this->transportSummary($from, $to),
        ];
    }

    /**
     * @return array<int, array{date: string, count: int, completed: int}>
     */
    public function bookingsByDay(int $days = 30): array
    {
        $since = now()->subDays(max(1, min(365, $days)))->startOfDay();

        $rows = Booking::query()
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->where('created_at', '>=', $since)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return $rows->map(fn ($row) => [
            'date' => (string) $row->day,
            'count' => (int) $row->total,
            'completed' => (int) $row->completed,
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function customerSummary(string $userId): array
    {
        $base = Booking::query()->where('customer_id', $userId);

        return [
            'bookings' => [
                'total' => (clone $base)->count(),
                'upcoming' => (clone $base)->whereIn('status', ['pending', 'accepted'])
                    ->where('scheduled_at', '>=', now())->count(),
                'completed' => (clone $base)->where('status', 'completed')->count(),
                'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
            ],
            'transport' => [
                'taxi_bookings' => TaxiBooking::query()->where('customer_id', $userId)->count(),
                'delivery_bookings' => DeliveryBooking::query()->where('customer_id', $userId)->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function transportSummary(Carbon $from, Carbon $to): array
    {
        $taxi = TaxiBooking::query()->whereBetween('created_at', [$from, $to]);
        $delivery = DeliveryBooking::query()->whereBetween('created_at', [$from, $to]);

        return [
            'taxi' => [
                'total_in_period' => (clone $taxi)->count(),
                'pending' => TaxiBooking::query()->where('status', 'pending')->count(),
                'completed_in_period' => (clone $taxi)->where('status', 'completed')->count(),
                'estimated_revenue_in_period' => (float) (clone $taxi)->where('status', 'completed')->sum('estimated_price'),
            ],
            'delivery' => [
                'total_in_period' => (clone $delivery)->count(),
                'pending' => DeliveryBooking::query()->where('status', 'pending')->count(),
                'completed_in_period' => (clone $delivery)->where('status', 'completed')->count(),
                'shipping_revenue_in_period' => (float) (clone $delivery)->where('status', 'completed')->sum('shipping_price'),
            ],
        ];
    }
}
