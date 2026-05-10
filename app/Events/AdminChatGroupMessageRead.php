<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminChatGroupMessageRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $groupId,
        public string $messageId,
        public string $readBy,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.'.$this->groupId)];
    }

    public function broadcastAs(): string
    {
        return 'message.read';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'chat_id'    => $this->groupId,
            'group_id'   => $this->groupId,
            'message_id' => $this->messageId,
            'read_by'    => $this->readBy,
        ];
    }
}
