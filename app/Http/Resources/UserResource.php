<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API shape for {@see \App\Models\User}.
 *
 * - **Self** (authenticated user viewing their own record): includes `role_name` and `permissions`.
 * - **Admin / super_admin** viewing another user: includes that user's `role_name` and `permissions`.
 * - **Anyone else** (e.g. nested client on a booking): omits `role_name` and `permissions` for privacy.
 *
 * Frontend: use `role_name` for top-level dashboard layout (customer / provider / admin); use `permissions`
 * to show or hide individual forms and actions. Server routes still enforce RBAC — this payload is for UX only.
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $includePermissions = $viewer && (
            $viewer->is($this->resource)
            || $viewer->hasRole(['super_admin', 'admin'])
        );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role_id' => $this->role_id,
            'role_name' => $this->when($includePermissions, fn () => $this->role?->name),
            'avatar_url' => $this->avatar_url,
            'email_verified_at' => $this->email_verified_at,
            'role' => new RoleResource($this->whenLoaded('role')),
            'provider' => new ProviderResource($this->whenLoaded('provider')),
            'permissions' => $this->when($includePermissions, fn () => $this->effectivePermissionNames()),
            'created_at' => $this->created_at,
        ];
    }
}
