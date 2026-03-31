<?php

namespace App\Mail;

use App\Models\Booking;
use App\Support\Frontend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public Booking $booking,
        public string $actionUrl = '',
    ) {
        $this->actionUrl = $actionUrl !== '' ? $actionUrl : Frontend::url('customer/bookings/'.$booking->id);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Reminder: booking #:id tomorrow', ['id' => $this->booking->id]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-reminder',
            with: [
                'recipientName' => $this->recipientName,
                'booking' => $this->booking,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}
