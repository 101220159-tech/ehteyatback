<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'bio' => $this->bio,
            'experience_years' => $this->experience_years,
            'rating_avg' => $this->rating_avg,
            'total_reviews' => $this->whenCounted('reviews'),
            'status' => $this->status,
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at,
            'distance_km' => $this->when(isset($this->distance_km), $this->distance_km),
            // Used by the map UI to place markers.
            // Note: lat/lng live on the related `users` table.
            'latitude' => $this->when($this->relationLoaded('user'), $this->user?->latitude),
            'longitude' => $this->when($this->relationLoaded('user'), $this->user?->longitude),
            'user' => new UserResource($this->whenLoaded('user')),
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'projects' => ProjectResource::collection($this->whenLoaded('projects')),
            'created_at' => $this->created_at,
        ];
    }
}
