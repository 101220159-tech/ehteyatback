<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Provider;
use App\Services\AvailabilityService;
use App\Services\BookingNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerBookingController extends Controller
{
    public function __construct(
        protected AvailabilityService $availability,
        protected BookingNotificationService $bookingNotifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::query()
            ->where('client_id', $request->user()->id)
            ->orderByDesc('booking_date')
            ->with(['provider.user', 'service'])
            ->paginate($request->integer('per_page', 15));

        return BookingResource::collection($bookings)->response();
    }

    public function store(BookingRequest $request): JsonResponse
    {
        $provider = Provider::query()->findOrFail($request->integer('provider_id'));

        $start = Carbon::parse($request->input('booking_date'));
        if (! $this->availability->isSlotAvailable($provider, $start, 60)) {
            throw ValidationException::withMessages(['booking_date' => ['This time slot is not available.']]);
        }

        $booking = Booking::query()->create([
            'client_id' => $request->user()->id,
            'provider_id' => $request->integer('provider_id'),
            'service_id' => $request->filled('service_id') ? $request->integer('service_id') : null,
            'booking_date' => $start,
            'status' => 'pending',
            'client_latitude' => $request->input('client_latitude'),
            'client_longitude' => $request->input('client_longitude'),
        ]);

        return (new BookingResource($booking->load(['provider.user', 'service'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $id): BookingResource
    {
        $booking = Booking::query()
            ->where('client_id', $request->user()->id)
            ->with(['provider.user', 'service'])
            ->findOrFail($id);

        return new BookingResource($booking);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $booking = Booking::query()
            ->where('client_id', $request->user()->id)
            ->findOrFail($id);

        if (! in_array($booking->status, ['pending', 'confirmed'], true)) {
            throw ValidationException::withMessages(['status' => ['Booking cannot be cancelled.']]);
        }

        $booking->update(['status' => 'cancelled']);
        $this->bookingNotifications->notifyCancelled($booking->fresh(['client', 'provider.user', 'service']));

        return response()->json(['message' => 'Booking cancelled.', 'booking' => new BookingResource($booking->fresh())]);
    }
}
