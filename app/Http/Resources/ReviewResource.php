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
            'id' => $this->id,
            'client_id' => $this->client_id,
            'provider_id' => $this->provider_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'client' => new UserResource($this->whenLoaded('client')),
            'provider' => new ProviderResource($this->whenLoaded('provider')),
            'created_at' => $this->created_at,
        ];
    }
}
