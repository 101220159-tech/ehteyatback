<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Models\ProviderEarning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderEarningsController extends Controller
{
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
            ],
        ]);
    }
}
