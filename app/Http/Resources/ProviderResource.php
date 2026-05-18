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
            'id'                      => $this->id,
            'user_id'                 => $this->user_id,
            'bio'                     => $this->bio,
            'experience_years'        => $this->experience_years,
            'rating_avg'              => $this->rating_avg,
            'total_reviews'           => $this->total_reviews,
            'reviews_count'           => $this->whenCounted('reviews'),
            'bookings_count'          => $this->whenCounted('bookings'),
            'completed_bookings_count'=> $this->whenCounted('completed_bookings_count'),
            'earnings_total'          => $this->when(
                isset($this->earnings_total),
                fn () => (float) $this->earnings_total,
            ),
            'is_active'               => $this->is_active,
            'is_busy'                 => $this->is_busy,
            'is_verified'             => $this->is_verified,
            'is_vip'                  => $this->is_vip,
            'allow_chat'              => $this->allow_chat,
            'subscription_expires_at' => $this->subscription_expires_at,
            'subscription_active'     => $this->hasActiveSubscription(),
            'verified_at'             => $this->verified_at,
            'avatar_url'        => $this->avatar_url,
            'latitude'          => $this->latitude,
            'longitude'         => $this->longitude,
            // Relations — always included when loaded, absent otherwise
            'user'              => new UserResource($this->whenLoaded('user')),
            'zones'             => $this->whenLoaded('zones'),
            'zones_count'       => $this->whenCounted('zones'),
            'services'          => ServiceResource::collection($this->whenLoaded('services')),
            'certifications'    => $this->whenLoaded('certifications'),
            'documents'         => $this->whenLoaded('documents'),
            'availability'      => $this->whenLoaded('availabilitySlots'),
            'projects'          => ProjectResource::collection($this->whenLoaded('projects')),
            'payments'          => $this->whenLoaded('payments'),
            'created_at'        => $this->created_at,
        ];
    }
}
