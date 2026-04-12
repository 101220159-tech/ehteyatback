<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'city_id' => $this->city_id,
            'is_default' => $this->is_default,
            'address_type' => $this->address_type,
            'phone' => $this->phone,
            'notes' => $this->notes,
            'city' => [
                'id' => $this->whenLoaded('city')?->id,
                'name' => $this->whenLoaded('city')?->name,
                'latitude' => $this->whenLoaded('city')?->latitude,
                'longitude' => $this->whenLoaded('city')?->longitude,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

