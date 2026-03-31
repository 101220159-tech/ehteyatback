<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderBookingController extends Controller
{
    public function __construct(
        protected BookingNotificationService $bookingNotifications,
    ) {}

    protected function provider(Request $request)
    {
        $p = $request->user()->provider;
        abort_if(! $p, 404, 'Provider profile not found.');

        return $p;
    }

    public function index(Request $request): JsonResponse
    {
        $provider = $this->provider($request);
        $bookings = Booking::query()
            ->where('provider_id', $provider->id)
            ->orderByDesc('booking_date')
            ->with(['client', 'service'])
            ->paginate($request->integer('per_page', 15));

        return BookingResource::collection($bookings)->response();
    }

    public function show(Request $request, int $id): BookingResource
    {
        $provider = $this->provider($request);
        $booking = Booking::query()
            ->where('provider_id', $provider->id)
            ->with(['client', 'service'])
            ->findOrFail($id);

        return new BookingResource($booking);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $provider = $this->provider($request);
        $booking = Booking::query()
            ->where('provider_id', $provider->id)
            ->findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:pending,confirmed,completed,cancelled,no_show'],
        ]);

        $previous = $booking->status;
        $booking->update($data);
        $fresh = $booking->fresh()->load(['client', 'provider.user', 'service']);

        if ($previous !== 'confirmed' && $fresh->status === 'confirmed') {
            $this->bookingNotifications->notifyConfirmed($fresh);
        }
        if ($previous !== 'cancelled' && $fresh->status === 'cancelled') {
            $this->bookingNotifications->notifyCancelled($fresh);
        }

        return response()->json(['message' => 'Status updated.', 'booking' => new BookingResource($fresh)]);
    }
}
