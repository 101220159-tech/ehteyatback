<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.'.$this->message->chat_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->message->id,
            'chat_id'    => $this->message->chat_id,
            'sender_id'  => $this->message->sender_id,
            'body'       => $this->message->body,
            'type'       => $this->message->type,
            'is_read'    => $this->message->read_at !== null,
            'read_at'    => $this->message->read_at,
            'created_at' => $this->message->created_at,
        ];
    }
}
