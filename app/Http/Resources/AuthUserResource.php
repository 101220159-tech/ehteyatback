<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Slim user payload for login / me — avoids heavy nested resources on auth.
 */
class AuthUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'phone'             => $this->phone,
            'role_id'           => $this->role_id,
            'role_name'         => $this->role?->name,
            'avatar_url'        => $this->avatar_url,
            'address'           => $this->address,
            'latitude'          => $this->latitude,
            'longitude'         => $this->longitude,
            'email_verified_at' => $this->email_verified_at,
            'permissions'       => $this->effectivePermissionNames(),
            'provider'          => $this->when(
                $this->relationLoaded('provider') && $this->provider,
                fn () => [
                    'id'          => $this->provider->id,
                    'is_verified' => (bool) $this->provider->is_verified,
                    'is_active'   => (bool) $this->provider->is_active,
                    'allow_chat'  => (bool) $this->provider->allow_chat,
                ]
            ),
            'created_at'        => $this->created_at,
        ];
    }
}
