<?php

namespace App\Mail;

use App\Support\Frontend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProviderVerification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $providerName,
        public string $actionUrl = '',
    ) {
        $this->actionUrl = $actionUrl !== '' ? $actionUrl : Frontend::url('provider/dashboard');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your provider profile is verified'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.provider-verification',
            with: [
                'providerName' => $this->providerName,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}
