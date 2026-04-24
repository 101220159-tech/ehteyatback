<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'customer_id'     => $this->customer_id,
            'provider_id'     => $this->provider_id,
            'unread_count'    => (int) ($this->unread_count ?? 0),
            'last_message_at' => $this->last_message_at,
            'customer'      => new UserResource($this->whenLoaded('customer')),
            'provider'      => new ProviderResource($this->whenLoaded('provider')),
            'messages'      => MessageResource::collection($this->whenLoaded('messages')),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
