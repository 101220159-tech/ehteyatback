<?php

namespace App\Mail;

use App\Support\Frontend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewMessageNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $chatUrl;

    public function __construct(
        public string $recipientName,
        public string $senderLabel,
        public string $messagePreview,
        public int $chatId,
        ?string $chatUrl = null,
    ) {
        $this->chatUrl = $chatUrl !== null && $chatUrl !== ''
            ? $chatUrl
            : Frontend::url('messages/'.$this->chatId);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('New message on :app', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-message',
            with: [
                'recipientName' => $this->recipientName,
                'senderLabel' => $this->senderLabel,
                'messagePreview' => $this->messagePreview,
                'chatUrl' => $this->chatUrl,
            ],
        );
    }
}
