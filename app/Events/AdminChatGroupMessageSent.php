<?php

namespace App\Events;

use App\Models\AdminChatGroupMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminChatGroupMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AdminChatGroupMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.'.$this->message->admin_chat_group_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $m = $this->message;

        return [
            'id'         => $m->id,
            'group_id'   => $m->admin_chat_group_id,
            'sender_id'  => $m->sender_id,
            'body'       => $m->body,
            'type'       => $m->type,
            'is_read'    => $m->read_at !== null,
            'read_at'    => $m->read_at,
            'created_at' => $m->created_at,
        ];
    }
}
