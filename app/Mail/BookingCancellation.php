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

class BookingCancellation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $actionUrlResolved;

    public function __construct(
        public string $recipientName,
        public Booking $booking,
        public string $cancellationNote = '',
        string $actionUrl = '',
    ) {
        $this->actionUrlResolved = $actionUrl !== '' ? $actionUrl : Frontend::url();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Booking #:id cancelled', ['id' => $this->booking->id]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-cancellation',
            with: [
                'recipientName' => $this->recipientName,
                'booking' => $this->booking,
                'cancellationNote' => $this->cancellationNote,
                'actionUrl' => $this->actionUrlResolved,
            ],
        );
    }
}
