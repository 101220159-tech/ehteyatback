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
     * Last 12 months: earned amount per month + running total (DB-agnostic aggregation).
     *
     * @return list<array{period: string, label: string, amount: float, cumulative: float, count: int}>
     */
    private function monthlyEarningsTrend(string $providerId): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(11);

        $rows = ProviderEarning::query()
            ->where('provider_id', $providerId)
            ->where('earned_at', '>=', $start)
            ->orderBy('earned_at')
            ->get(['amount', 'earned_at']);

        $totalsByMonth = [];
        $countsByMonth = [];
        foreach ($rows as $row) {
            if (! $row->earned_at) {
                continue;
            }
            $key = $row->earned_at->copy()->timezone(config('app.timezone'))->format('Y-m');
            $amt = (float) $row->amount;
            $totalsByMonth[$key] = ($totalsByMonth[$key] ?? 0) + $amt;
            $countsByMonth[$key] = ($countsByMonth[$key] ?? 0) + 1;
        }

        $series = [];
        $cumulative = 0.0;
        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');
            $amount = round((float) ($totalsByMonth[$key] ?? 0), 2);
            $cumulative = round($cumulative + $amount, 2);
            $series[] = [
                'period'     => $key,
                'label'      => $month->format('M Y'),
                'amount'     => $amount,
                'cumulative' => $cumulative,
                'count'      => (int) ($countsByMonth[$key] ?? 0),
            ];
        }

        return $series;
    }

    public function index(Request $request): JsonResponse
    {
        $provider = $request->user()->provider;
        abort_if(! $provider, 404, 'Provider profile not found.');

        $earnings = ProviderEarning::where('provider_id', $provider->id)
            ->with(['customer:id,name,phone,avatar_url', 'booking.service'])
            ->orderByDesc('earned_at')
            ->paginate($request->integer('per_page', 20));

        $total = ProviderEarning::where('provider_id', $provider->id)->sum('amount');

        return response()->json([
            'data' => $earnings->map(fn ($e) => [
                'id'        => $e->id,
                'amount'    => (float) $e->amount,
                'earned_at' => $e->earned_at,
                'customer'  => $e->customer ? [
                    'id'         => $e->customer->id,
                    'name'       => $e->customer->name,
                    'phone'      => $e->customer->phone,
                    'avatar_url' => $e->customer->avatar_url,
                ] : null,
                'booking'   => $e->booking ? [
                    'id'           => $e->booking->id,
                    'scheduled_at' => $e->booking->scheduled_at,
                    'service'      => $e->booking->service?->name,
                ] : null,
            ]),
            'meta'    => [
                'current_page' => $earnings->currentPage(),
                'last_page'    => $earnings->lastPage(),
                'total'        => $earnings->total(),
            ],
            'summary' => [
                'total_earned' => (float) $total,
                'chart'        => $this->monthlyEarningsTrend($provider->id),
            ],
        ]);
    }
}
