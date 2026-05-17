<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ProviderScheduleResource;
use App\Models\Booking;
use App\Models\BookingRescheduleRequest;
use App\Models\ProviderEarning;
use App\Models\ProviderSchedule;
use App\Services\NotificationService;
use App\Services\ProviderScheduleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProviderBookingController extends Controller
{
    public function __construct(
        private NotificationService $notify,
        private ProviderScheduleService $scheduleService,
    ) {}

    private function provider(Request $request)
    {
        $p = $request->user()->provider;
        abort_if(! $p, 404, 'Provider profile not found.');

        return $p;
    }

    public function index(Request $request): JsonResponse
    {
        $provider = $this->provider($request);

        $bookings = Booking::where('provider_id', $provider->id)
            ->with(['customer', 'service', 'latestRescheduleRequest'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest('scheduled_at')
            ->paginate(min($request->integer('per_page', 15), 500));

        return response()->json(['data' => $bookings]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $provider = $this->provider($request);
        $booking  = Booking::where('provider_id', $provider->id)
            ->with(['customer', 'service', 'review', 'rescheduleRequests.requestedBy'])
            ->findOrFail($id);

        return response()->json(['data' => $booking]);
    }

    public function accept(Request $request, string $id): JsonResponse
    {
        $provider = $this->provider($request);

        $schedule = DB::transaction(function () use ($request, $provider, $id) {
            $booking = Booking::where('provider_id', $provider->id)
                ->where('status', 'pending')
                ->with(['customer', 'service'])
                ->lockForUpdate()
                ->findOrFail($id);

            $startsAt = Carbon::parse($booking->scheduled_at);
            $this->scheduleService->assertNoConflict(
                $provider->id,
                $startsAt,
                (int) ($booking->duration_minutes ?? 60),
                $booking->id
            );

            $booking->update(['status' => 'accepted', 'accepted_at' => now()]);

            $schedule = $this->scheduleService->syncFromBooking($booking->fresh());

            if ($booking->customer) {
                $this->notify->sendInApp(
                    $booking->customer,
                    'booking_accepted',
                    'Booking Accepted',
                    'Your booking for "'.$booking->service?->name.'" has been accepted.',
                    ['booking_id' => $booking->id]
                );
            }

            return $schedule->load(['booking.customer', 'booking.service']);
        });

        return response()->json([
            'message'  => 'Booking accepted and added to your schedule.',
            'data'     => new BookingResource($schedule->booking),
            'schedule' => new ProviderScheduleResource($schedule),
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $provider = $this->provider($request);
        $data     = $request->validate(['reason' => 'nullable|string|max:500']);
        $booking  = Booking::where('provider_id', $provider->id)
            ->where('status', 'pending')
            ->with(['customer', 'service'])
            ->findOrFail($id);

        $booking->update(['status' => 'rejected', 'cancelled_at' => now()]);

        ProviderSchedule::where('booking_id', $booking->id)->update(['status' => 'cancelled']);

        if ($booking->customer) {
            $this->notify->sendInApp(
                $booking->customer,
                'booking_rejected',
                'Booking Rejected',
                'Your booking for "'.$booking->service?->name.'" was rejected.'.($data['reason'] ? ' Reason: '.$data['reason'] : ''),
                ['booking_id' => $booking->id]
            );
        }

        return response()->json(['message' => 'Booking rejected.', 'data' => $booking->fresh()]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $provider = $this->provider($request);
        $data     = $request->validate(['reason' => 'nullable|string|max:500']);

        $schedule = DB::transaction(function () use ($request, $provider, $id, $data) {
            $booking = Booking::where('provider_id', $provider->id)
                ->whereIn('status', ['pending', 'accepted', 'reschedule_requested'])
                ->with(['customer', 'service'])
                ->lockForUpdate()
                ->findOrFail($id);

            $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            $schedule = $this->scheduleService->syncFromBooking($booking->fresh());

            if ($booking->customer) {
                $this->notify->sendInApp(
                    $booking->customer,
                    'booking_cancelled',
                    'Booking Cancelled',
                    'Your booking for "'.$booking->service?->name.'" was cancelled by the provider.'
                        .($data['reason'] ? ' Reason: '.$data['reason'] : ''),
                    ['booking_id' => $booking->id]
                );
            }

            return $schedule ?? null;
        });

        return response()->json([
            'message'  => 'Booking cancelled.',
            'schedule' => $schedule ? new ProviderScheduleResource($schedule->load(['booking.customer', 'booking.service'])) : null,
        ]);
    }

    public function requestReschedule(Request $request, string $id): JsonResponse
    {
        $provider = $this->provider($request);
        $data     = $request->validate([
            'proposed_at' => 'required|date|after:now',
            'message'     => 'nullable|string|max:500',
        ]);

        $booking = Booking::where('provider_id', $provider->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->with(['customer', 'service'])
            ->findOrFail($id);

        BookingRescheduleRequest::create([
            'booking_id'   => $booking->id,
            'requested_by' => $request->user()->id,
            'proposed_at'  => $data['proposed_at'],
            'message'      => $data['message'] ?? null,
            'status'       => 'pending',
        ]);

        $booking->update(['status' => 'reschedule_requested']);

        if ($booking->customer) {
            $this->notify->sendInApp(
                $booking->customer,
                'booking_reschedule',
                'Reschedule Requested',
                'The provider proposed a new time for your "'.$booking->service?->name.'" booking.',
                ['booking_id' => $booking->id]
            );
        }

        return response()->json(['message' => 'Reschedule proposal sent to customer.', 'data' => $booking->fresh()]);
    }

    public function complete(Request $request, string $id): JsonResponse
    {
        $provider = $this->provider($request);

        $result = DB::transaction(function () use ($request, $provider, $id) {
            $booking = Booking::where('provider_id', $provider->id)
                ->where('status', 'accepted')
                ->with(['customer', 'service'])
                ->lockForUpdate()
                ->findOrFail($id);

            $data        = $request->validate(['amount' => ['nullable', 'numeric', 'min:0']]);
            $completedAt = now();
            $amount      = $data['amount'] ?? $booking->price ?? 0;

            $booking->update(['status' => 'completed', 'completed_at' => $completedAt]);
            $provider->update(['is_busy' => false]);

            ProviderEarning::firstOrCreate(
                ['booking_id' => $booking->id],
                [
                    'provider_id' => $provider->id,
                    'customer_id' => $booking->customer_id,
                    'amount'      => $amount,
                    'earned_at'   => $completedAt,
                ]
            );

            $schedule = $this->scheduleService->syncFromBooking($booking->fresh());

            if ($booking->customer) {
                $this->notify->sendInApp(
                    $booking->customer,
                    'booking_completed',
                    'Service Completed',
                    'Your "'.$booking->service?->name.'" booking is completed. Please leave a review!',
                    ['booking_id' => $booking->id]
                );
            }

            return ['booking' => $booking->fresh(), 'schedule' => $schedule];
        });

        return response()->json([
            'message'  => 'Booking marked as completed.',
            'data'     => new BookingResource($result['booking']),
            'schedule' => new ProviderScheduleResource($result['schedule']),
        ]);
    }
}
