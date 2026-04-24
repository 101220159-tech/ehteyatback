<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'booking_id'  => $this->booking_id,
            'customer_id' => $this->customer_id,
            'provider_id' => $this->provider_id,
            'rating'      => $this->rating,
            'comment'     => $this->comment,
            'customer'    => new UserResource($this->whenLoaded('customer')),
            'provider'    => new ProviderResource($this->whenLoaded('provider')),
            'booking'     => $this->whenLoaded('booking', fn () => [
                'id'           => $this->booking->id,
                'scheduled_at' => $this->booking->scheduled_at,
                'service'      => $this->booking->service ? [
                    'id'   => $this->booking->service->id,
                    'name' => $this->booking->service->name,
                ] : null,
            ]),
            'created_at'  => $this->created_at,
        ];
    }
}
