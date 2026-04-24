<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ProviderEarning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderDashboardController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $provider = $request->user()->provider;
        abort_if(! $provider, 404, 'Provider profile not found.');

        $pid = $provider->id;

        return response()->json([
            // Bookings
            'total_bookings'       => Booking::where('provider_id', $pid)->count(),
            'pending_bookings'     => Booking::where('provider_id', $pid)->where('status', 'pending')->count(),
            'upcoming_bookings'    => Booking::where('provider_id', $pid)->where('status', 'accepted')->where('scheduled_at', '>=', now())->count(),
            'completed_bookings'   => Booking::where('provider_id', $pid)->where('status', 'completed')->count(),
            'cancelled_bookings'   => Booking::where('provider_id', $pid)->where('status', 'cancelled')->count(),
            'completed_this_month' => Booking::where('provider_id', $pid)->where('status', 'completed')->whereMonth('completed_at', now()->month)->whereYear('completed_at', now()->year)->count(),

            // Earnings
            'earnings_this_month'  => (float) ProviderEarning::where('provider_id', $pid)->whereMonth('earned_at', now()->month)->whereYear('earned_at', now()->year)->sum('amount'),
            'total_earned'         => (float) ProviderEarning::where('provider_id', $pid)->sum('amount'),

            // Ratings
            'rating_avg'           => (float) $provider->rating_avg,
            'total_reviews'        => $provider->reviews()->count(),
        ]);
    }
}
