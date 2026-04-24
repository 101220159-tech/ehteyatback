<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $chatId,
        public string $messageId,
        public string $readBy,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.'.$this->chatId)];
    }

    public function broadcastAs(): string
    {
        return 'message.read';
    }

    public function broadcastWith(): array
    {
        return [
            'chat_id'    => $this->chatId,
            'message_id' => $this->messageId,
            'read_by'    => $this->readBy,
        ];
    }
}
