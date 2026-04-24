<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRescheduleRequest;
use App\Models\Provider;
use App\Models\ProviderAvailability;
use App\Services\BookingNotificationService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * @group Customer — Bookings
 */
class CustomerBookingController extends Controller
{
    public function __construct(
        private BookingNotificationService $notifier,
        private NotificationService $notify,
    ) {}

    /**
     * List own bookings.
     *
     * @queryParam status string Filter by status. Example: pending
     */
    public function index(Request $request): JsonResponse
    {
        $bookings = $request->user()
            ->bookings()
            ->with(['provider.user', 'service', 'latestRescheduleRequest'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest('scheduled_at')
            ->paginate(15);

        return response()->json(['data' => $bookings]);
    }

    /**
     * Create a booking.
     *
     * Validates provider availability and prevents time conflicts.
     *
     * @bodyParam provider_id string required Provider UUID.
     * @bodyParam service_id string required Service UUID.
     * @bodyParam scheduled_at string required ISO 8601 datetime. Example: 2026-05-01T10:00:00
     * @bodyParam duration_minutes integer optional Default: 60
     * @bodyParam customer_notes string optional
     * @bodyParam customer_latitude number optional
     * @bodyParam customer_longitude number optional
     * @bodyParam customer_address string optional
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_id'        => 'required|uuid|exists:providers,id',
            'service_id'         => 'required|uuid|exists:services,id',
            'scheduled_at'       => 'required|date|after:-10 minutes',
            'duration_minutes'   => 'nullable|integer|min:15|max:480',
            'customer_notes'     => 'nullable|string|max:1000',
            'customer_latitude'  => 'nullable|numeric',
            'customer_longitude' => 'nullable|numeric',
            'customer_address'   => 'nullable|string|max:500',
        ]);

        $provider    = Provider::findOrFail($data['provider_id']);
        $scheduledAt = Carbon::parse($data['scheduled_at']);

        $providerService = \App\Models\ProviderService::where('provider_id', $provider->id)
            ->where('service_id', $data['service_id'])
            ->first();

        $price = $providerService?->price ?? \App\Models\Service::findOrFail($data['service_id'])->base_price;
        $duration    = $data['duration_minutes'] ?? 60;

        if (! $provider->is_active) {
            return response()->json(['message' => 'This provider is not currently active.'], 422);
        }

        // Validate within availability slot — only when the provider has configured a schedule
        $hasSchedule = ProviderAvailability::where('provider_id', $provider->id)->exists();

        if ($hasSchedule) {
            $dayOfWeek = strtolower($scheduledAt->format('l')); // e.g. 'thursday'
            $timeStr   = $scheduledAt->format('H:i:s');

            $available = ProviderAvailability::where('provider_id', $provider->id)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_available', true)
                ->where('start_time', '<=', $timeStr)
                ->where('end_time', '>=', $timeStr)
                ->exists();

            if (! $available) {
                return response()->json(['message' => 'Provider is not available at the requested time.'], 422);
            }
        }

        // Prevent time conflicts with accepted bookings
        $endTime = $scheduledAt->copy()->addMinutes($duration);
        $conflict = Booking::where('provider_id', $provider->id)
            ->where('status', 'accepted')
            ->where(function ($q) use ($scheduledAt, $endTime) {
                $q->whereBetween('scheduled_at', [$scheduledAt, $endTime])
                  ->orWhereRaw(
                      "DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) BETWEEN ? AND ?",
                      [$scheduledAt, $endTime]
                  );
            })
            ->exists();

        if ($conflict) {
            return response()->json(['message' => 'This time slot is already taken.'], 422);
        }

        $booking = Booking::create([
            'customer_id'        => $request->user()->id,
            'provider_id'        => $provider->id,
            'service_id'         => $data['service_id'],
            'price'              => $price,
            'scheduled_at'       => $scheduledAt,
            'duration_minutes'   => $duration,
            'customer_notes'     => $data['customer_notes'] ?? null,
            'customer_latitude'  => $data['customer_latitude'] ?? null,
            'customer_longitude' => $data['customer_longitude'] ?? null,
            'customer_address'   => $data['customer_address'] ?? null,
            'status'             => 'pending',
        ]);

        // Notify provider of new booking
        $booking->load(['provider.user', 'service']);
        if ($provider->user) {
            $this->notify->sendInApp(
                $provider->user,
                'booking_new',
                'New Booking Request',
                $request->user()->name.' requested a booking for "'.$booking->service?->name.'".',
                ['booking_id' => $booking->id]
            );
        }

        return response()->json([
            'message' => 'Booking created. Awaiting provider confirmation.',
            'data'    => $booking,
        ], 201);
    }

    /**
     * Show a single booking.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $booking = $request->user()->bookings()
            ->with(['provider.user', 'service', 'review', 'rescheduleRequests.requestedBy'])
            ->findOrFail($id);

        return response()->json(['data' => $booking]);
    }

    /**
     * Cancel a booking (only while pending or reschedule_requested).
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $booking = $request->user()->bookings()
            ->whereIn('status', ['pending', 'reschedule_requested'])
            ->with(['provider.user', 'service'])
            ->findOrFail($id);

        $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        // Notify provider
        if ($booking->provider?->user) {
            $this->notify->sendInApp(
                $booking->provider->user,
                'booking_cancelled',
                'Booking Cancelled',
                $request->user()->name.' cancelled the booking for "'.$booking->service?->name.'".',
                ['booking_id' => $booking->id]
            );
        }

        return response()->json(['message' => 'Booking cancelled.', 'data' => $booking->fresh()]);
    }

    /**
     * Accept a reschedule proposal from the provider.
     */
    public function acceptReschedule(Request $request, string $id): JsonResponse
    {
        $booking = $request->user()->bookings()
            ->where('status', 'reschedule_requested')
            ->with('latestRescheduleRequest')
            ->findOrFail($id);

        $reschedule = $booking->latestRescheduleRequest;

        if (! $reschedule || $reschedule->status !== 'pending') {
            return response()->json(['message' => 'No pending reschedule request.'], 422);
        }

        $reschedule->update(['status' => 'accepted', 'responded_at' => now()]);
        $booking->update([
            'scheduled_at' => $reschedule->proposed_at,
            'status'       => 'accepted',
            'accepted_at'  => now(),
        ]);

        try {
            $this->notifier->notifyConfirmed($booking);
        } catch (\Throwable $e) {
            Log::warning('Reschedule notification failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Reschedule accepted.', 'data' => $booking->fresh()]);
    }

    /**
     * Reject a reschedule proposal from the provider.
     */
    public function rejectReschedule(Request $request, string $id): JsonResponse
    {
        $booking = $request->user()->bookings()
            ->where('status', 'reschedule_requested')
            ->with('latestRescheduleRequest')
            ->findOrFail($id);

        $reschedule = $booking->latestRescheduleRequest;

        if (! $reschedule || $reschedule->status !== 'pending') {
            return response()->json(['message' => 'No pending reschedule request.'], 422);
        }

        $reschedule->update(['status' => 'rejected', 'responded_at' => now()]);
        $booking->update(['status' => 'pending']);

        try {
            $this->notifier->notifyCancelled($booking, 'Reschedule rejected by customer.');
        } catch (\Throwable $e) {
            Log::warning('Reschedule rejection notification failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Reschedule rejected. Booking returned to pending.']);
    }
}
