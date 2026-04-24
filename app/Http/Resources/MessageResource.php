<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'chat_id'   => $this->chat_id,
            'sender_id' => $this->sender_id,
            'body'      => $this->body,
            'type'      => $this->type,
            'is_read'   => $this->read_at !== null,
            'read_at'   => $this->read_at,
            'created_at'=> $this->created_at,
        ];
    }
}
