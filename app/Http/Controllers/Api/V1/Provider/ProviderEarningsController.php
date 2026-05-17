<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Models\ProviderEarning;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderEarningsController extends Controller
{
    /**
     * @return list<array{period: string, label: string, amount: float, cumulative: float, count: int}>
     */
    private function earningsTrend(string $providerId, string $interval = 'month'): array
    {
        return match ($interval) {
            'day' => $this->dailyTrend($providerId, 30),
            'week' => $this->weeklyTrend($providerId, 12),
            default => $this->monthlyTrend($providerId, 12),
        };
    }

    /**
     * @return list<array{period: string, label: string, amount: float, cumulative: float, count: int}>
     */
    private function dailyTrend(string $providerId, int $days): array
    {
        $days = max(7, min(90, $days));
        $start = Carbon::now()->startOfDay()->subDays($days - 1);
        $end = Carbon::now()->endOfDay();

        $totals = $this->aggregateAmountsByKey(
            $providerId,
            $start,
            $end,
            fn (Carbon $dt) => $dt->format('Y-m-d'),
        );

        $series = [];
        $cumulative = 0.0;
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $amount = round((float) ($totals['amounts'][$key] ?? 0), 2);
            $cumulative = round($cumulative + $amount, 2);
            $series[] = [
                'period' => $key,
                'label' => $day->format('M j'),
                'amount' => $amount,
                'cumulative' => $cumulative,
                'count' => (int) ($totals['counts'][$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{period: string, label: string, amount: float, cumulative: float, count: int}>
     */
    private function weeklyTrend(string $providerId, int $weeks): array
    {
        $weeks = max(4, min(52, $weeks));
        $start = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeeks($weeks - 1);
        $end = Carbon::now()->endOfDay();

        $totals = $this->aggregateAmountsByKey(
            $providerId,
            $start,
            $end,
            fn (Carbon $dt) => $dt->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
        );

        $series = [];
        $cumulative = 0.0;
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $key = $weekStart->format('Y-m-d');
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
            $amount = round((float) ($totals['amounts'][$key] ?? 0), 2);
            $cumulative = round($cumulative + $amount, 2);
            $series[] = [
                'period' => $key,
                'label' => $weekStart->format('M j').' – '.$weekEnd->format('M j'),
                'amount' => $amount,
                'cumulative' => $cumulative,
                'count' => (int) ($totals['counts'][$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{period: string, label: string, amount: float, cumulative: float, count: int}>
     */
    private function monthlyTrend(string $providerId, int $months): array
    {
        $months = max(3, min(24, $months));
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);
        $end = Carbon::now()->endOfDay();

        $totals = $this->aggregateAmountsByKey(
            $providerId,
            $start,
            $end,
            fn (Carbon $dt) => $dt->format('Y-m'),
        );

        $series = [];
        $cumulative = 0.0;
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');
            $amount = round((float) ($totals['amounts'][$key] ?? 0), 2);
            $cumulative = round($cumulative + $amount, 2);
            $series[] = [
                'period' => $key,
                'label' => $month->format('M Y'),
                'amount' => $amount,
                'cumulative' => $cumulative,
                'count' => (int) ($totals['counts'][$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return array{amounts: array<string, float>, counts: array<string, int>}
     */
    private function aggregateAmountsByKey(
        string $providerId,
        Carbon $start,
        Carbon $end,
        callable $keyFn,
    ): array {
        $rows = ProviderEarning::query()
            ->where('provider_id', $providerId)
            ->whereBetween('earned_at', [$start, $end])
            ->orderBy('earned_at')
            ->get(['amount', 'earned_at']);

        $amounts = [];
        $counts = [];
        $tz = config('app.timezone');

        foreach ($rows as $row) {
            if (! $row->earned_at) {
                continue;
            }
            $key = $keyFn($row->earned_at->copy()->timezone($tz));
            $amt = (float) $row->amount;
            $amounts[$key] = ($amounts[$key] ?? 0) + $amt;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return ['amounts' => $amounts, 'counts' => $counts];
    }

    public function index(Request $request): JsonResponse
    {
        $provider = $request->user()->provider;
        abort_if(! $provider, 404, 'Provider profile not found.');

        $earnings = ProviderEarning::where('provider_id', $provider->id)
            ->with(['customer:id,name,phone,avatar_url', 'booking.service'])
            ->orderByDesc('earned_at')
            ->paginate($request->integer('per_page', 20));

        $total = (float) ProviderEarning::where('provider_id', $provider->id)->sum('amount');
        $completedCount = ProviderEarning::where('provider_id', $provider->id)->count();

        $charts = [
            'day' => $this->earningsTrend($provider->id, 'day'),
            'week' => $this->earningsTrend($provider->id, 'week'),
            'month' => $this->earningsTrend($provider->id, 'month'),
        ];

        return response()->json([
            'data' => $earnings->map(fn ($e) => [
                'id' => $e->id,
                'amount' => (float) $e->amount,
                'earned_at' => $e->earned_at,
                'customer' => $e->customer ? [
                    'id' => $e->customer->id,
                    'name' => $e->customer->name,
                    'phone' => $e->customer->phone,
                    'avatar_url' => $e->customer->avatar_url,
                ] : null,
                'booking' => $e->booking ? [
                    'id' => $e->booking->id,
                    'scheduled_at' => $e->booking->scheduled_at,
                    'service' => $e->booking->service?->name,
                ] : null,
            ]),
            'meta' => [
                'current_page' => $earnings->currentPage(),
                'last_page' => $earnings->lastPage(),
                'total' => $earnings->total(),
            ],
            'summary' => [
                'total_earned' => $total,
                'completed_count' => $completedCount,
                'charts' => $charts,
                'chart' => $charts['month'],
            ],
        ]);
    }

    public function chart(Request $request): JsonResponse
    {
        $provider = $request->user()->provider;
        abort_if(! $provider, 404, 'Provider profile not found.');

        $interval = $request->input('interval', 'day');
        if (! in_array($interval, ['day', 'week', 'month'], true)) {
            $interval = 'day';
        }

        return response()->json([
            'success' => true,
            'data' => $this->earningsTrend($provider->id, $interval),
        ]);
    }
}
