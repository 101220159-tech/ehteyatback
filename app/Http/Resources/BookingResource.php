<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'provider_id' => $this->provider_id,
            'service_id' => $this->service_id,
            'booking_date' => $this->booking_date,
            'status' => $this->status,
            'client_latitude' => $this->client_latitude,
            'client_longitude' => $this->client_longitude,
            'reminder_sent_at' => $this->reminder_sent_at,
            'client' => new UserResource($this->whenLoaded('client')),
            'provider' => new ProviderResource($this->whenLoaded('provider')),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'created_at' => $this->created_at,
        ];
    }
}
