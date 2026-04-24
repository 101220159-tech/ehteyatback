<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'email'              => $this->email,
            'phone'              => $this->phone,
            'role_id'            => $this->role_id,
            'role_name'          => $this->role?->name,
            'avatar_url'         => $this->avatar_url,
            'address'            => $this->address,
            'latitude'           => $this->latitude,
            'longitude'          => $this->longitude,
            'email_verified_at'  => $this->email_verified_at,
            'role'               => new RoleResource($this->whenLoaded('role')),
            'provider'           => new ProviderResource($this->whenLoaded('provider')),
            // Permissions directly assigned to this user in the user_permissions table
            'direct_permissions' => $this->whenLoaded('directPermissions', fn () =>
                $this->directPermissions->map(fn ($p) => [
                    'id'          => $p->id,
                    'name'        => $p->name,
                    'description' => $p->description,
                ])->values()
            ),
            // Effective permissions = role permissions + direct permissions (merged, unique)
            'permissions'        => $this->effectivePermissionNames(),
            'created_at'         => $this->created_at,
        ];
    }
}
