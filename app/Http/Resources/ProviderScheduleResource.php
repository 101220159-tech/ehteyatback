<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $booking = $this->whenLoaded('booking');
        $startsAt = Carbon::parse($this->scheduled_date->format('Y-m-d').' '.$this->scheduled_time);

        return [
            'id'               => $this->id,
            'provider_id'      => $this->provider_id,
            'booking_id'       => $this->booking_id,
            'scheduled_date'   => $this->scheduled_date->format('Y-m-d'),
            'scheduled_time'   => substr((string) $this->scheduled_time, 0, 5),
            'duration_minutes' => $this->duration_minutes,
            'status'           => $this->status,
            'starts_at'        => $startsAt->toIso8601String(),
            'ends_at'          => $startsAt->copy()->addMinutes($this->duration_minutes)->toIso8601String(),
            'customer_name'    => $booking?->customer?->name,
            'service_name'     => $booking?->service?->name,
            'address'          => $booking?->customer_address,
            'notes'            => $booking?->customer_notes,
            'booking'          => $booking ? new BookingResource($booking) : null,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
