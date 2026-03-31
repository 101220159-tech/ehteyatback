<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderEarningsController extends Controller
{
    /**
     * Completed bookings list. Pricing is on provider_services / offline settlement — not stored per booking in the schema.
     */
    public function index(Request $request): JsonResponse
    {
        $provider = $request->user()->provider;
        abort_if(! $provider, 404, 'Provider profile not found.');

        $bookings = Booking::query()
            ->where('provider_id', $provider->id)
            ->where('status', 'completed')
            ->orderByDesc('booking_date')
            ->with(['client', 'service'])
            ->paginate($request->integer('per_page', 20));

        $count = Booking::query()
            ->where('provider_id', $provider->id)
            ->where('status', 'completed')
            ->count();

        return BookingResource::collection($bookings)->additional([
            'summary' => [
                'completed_bookings_count' => $count,
            ],
        ])->response();
    }
}
