<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerDashboardController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'upcoming_bookings' => BookingResource::collection(
                Booking::query()
                    ->where('client_id', $user->id)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where('booking_date', '>=', now())
                    ->orderBy('booking_date')
                    ->limit(5)
                    ->with(['provider.user', 'service'])
                    ->get()
            ),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $bookings = Booking::query()
            ->where('client_id', $request->user()->id)
            ->whereIn('status', ['completed', 'cancelled', 'no_show'])
            ->orderByDesc('booking_date')
            ->with(['provider.user', 'service'])
            ->paginate($request->integer('per_page', 15));

        return BookingResource::collection($bookings)->response();
    }
}
