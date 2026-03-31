<?php

namespace App\Services;

use App\Mail\BookingCancellation;
use App\Mail\BookingConfirmation;
use App\Models\Booking;
use App\Support\Frontend;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class BookingNotificationService
{
    public function __construct(
        protected FirebaseService $firebase,
    ) {}

    public function notifyConfirmed(Booking $booking): void
    {
        $booking->loadMissing(['customer', 'provider.user', 'service']);

        try {
            if ($booking->customer) {
                Mail::to($booking->customer->email)->queue(new BookingConfirmation(
                    $booking->customer->name,
                    $booking
                ));

                $this->firebase->sendToUser(
                    $booking->customer,
                    __('Booking confirmed'),
                    __('Your booking #:id is confirmed.', ['id' => $booking->id]),
                    ['type' => 'booking_confirmed', 'booking_id' => (string) $booking->id]
                );
            }

            if ($booking->provider?->user) {
                $this->firebase->sendToUser(
                    $booking->provider->user,
                    __('Booking confirmed'),
                    __('Booking #:id is confirmed.', ['id' => $booking->id]),
                    ['type' => 'booking_confirmed', 'booking_id' => (string) $booking->id]
                );
            }
        } catch (Throwable $e) {
            Log::error('Booking confirmation notification failed.', ['exception' => $e->getMessage()]);
        }
    }

    public function notifyCancelled(Booking $booking, string $note = ''): void
    {
        $booking->loadMissing(['customer', 'provider.user', 'service']);

        try {
            if ($booking->customer) {
                Mail::to($booking->customer->email)->queue(new BookingCancellation(
                    $booking->customer->name,
                    $booking,
                    $note,
                    Frontend::url()
                ));

                $this->firebase->sendToUser(
                    $booking->customer,
                    __('Booking cancelled'),
                    __('Booking #:id was cancelled.', ['id' => $booking->id]),
                    ['type' => 'booking_cancelled', 'booking_id' => (string) $booking->id]
                );
            }

            if ($booking->provider?->user) {
                Mail::to($booking->provider->user->email)->queue(new BookingCancellation(
                    $booking->provider->user->name,
                    $booking,
                    $note,
                    Frontend::url('provider/bookings/'.$booking->id)
                ));

                $this->firebase->sendToUser(
                    $booking->provider->user,
                    __('Booking cancelled'),
                    __('Booking #:id was cancelled.', ['id' => $booking->id]),
                    ['type' => 'booking_cancelled', 'booking_id' => (string) $booking->id]
                );
            }
        } catch (Throwable $e) {
            Log::error('Booking cancellation notification failed.', ['exception' => $e->getMessage()]);
        }
    }
}
