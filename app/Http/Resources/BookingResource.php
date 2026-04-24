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
            'id'                 => $this->id,
            'customer_id'        => $this->customer_id,
            'provider_id'        => $this->provider_id,
            'service_id'         => $this->service_id,
            'price'              => $this->price !== null ? (float) $this->price : null,
            'scheduled_at'       => $this->scheduled_at,
            'duration_minutes'   => $this->duration_minutes,
            'status'             => $this->status,
            'customer_notes'     => $this->customer_notes,
            'customer_latitude'  => $this->customer_latitude,
            'customer_longitude' => $this->customer_longitude,
            'customer_address'   => $this->customer_address,
            'accepted_at'        => $this->accepted_at,
            'completed_at'       => $this->completed_at,
            'cancelled_at'       => $this->cancelled_at,
            'reminder_sent_at'   => $this->reminder_sent_at,
            'customer'           => new UserResource($this->whenLoaded('customer')),
            'provider'           => new ProviderResource($this->whenLoaded('provider')),
            'service'            => new ServiceResource($this->whenLoaded('service')),
            'review'             => $this->whenLoaded('review', fn () => $this->review ? [
                'id'      => $this->review->id,
                'rating'  => $this->review->rating,
                'comment' => $this->review->comment,
            ] : null),
            'earning'            => $this->whenLoaded('earning', fn () => [
                'amount'    => $this->earning ? (float) $this->earning->amount : null,
                'earned_at' => $this->earning?->earned_at,
            ]),
            'created_at'         => $this->created_at,
        ];
    }
}
