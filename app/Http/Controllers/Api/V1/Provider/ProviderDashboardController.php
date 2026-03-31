<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderDashboardController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $provider = $request->user()->provider;
        abort_if(! $provider, 404, 'Provider profile not found.');

        return response()->json([
            'pending_bookings' => Booking::query()->where('provider_id', $provider->id)->where('status', 'pending')->count(),
            'upcoming' => Booking::query()->where('provider_id', $provider->id)->whereIn('status', ['confirmed'])->where('booking_date', '>=', now())->count(),
            'completed_this_month' => Booking::query()->where('provider_id', $provider->id)->where('status', 'completed')->whereMonth('booking_date', now()->month)->count(),
            'rating_avg' => $provider->rating_avg,
            'total_reviews' => $provider->reviews()->count(),
        ]);
    }
}
