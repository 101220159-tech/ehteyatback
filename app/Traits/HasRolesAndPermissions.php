<?php

namespace App\Traits;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRolesAndPermissions
{
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    public function assignRole(Role|string $role): void
    {
        $this->update(['role_id' => $this->resolveRoleId($role)]);
    }

    public function hasRole(string|array $roles): bool
    {
        $names = is_array($roles) ? $roles : [$roles];
        $role  = $this->relationLoaded('role') ? $this->role : $this->role()->first();

        return $role && in_array($role->name, $names, true);
    }

    public function hasPermission(string|array $permissions): bool
    {
        $names = is_array($permissions) ? $permissions : [$permissions];
        foreach ($names as $name) {
            if (! $this->hasSinglePermission($name)) {
                return false;
            }
        }

        return true;
    }

    public function hasAnyPermission(array $names): bool
    {
        foreach ($names as $name) {
            if ($this->hasSinglePermission($name)) {
                return true;
            }
        }

        return false;
    }

    protected function hasSinglePermission(string $name): bool
    {
        if ($this->directPermissions()->where('permissions.name', $name)->exists()) {
            return true;
        }

        $role = $this->relationLoaded('role') ? $this->role : $this->role()->first();

        return $role && $role->permissions()->where('permissions.name', $name)->exists();
    }

    public function givePermissionTo(string|Permission $permission): void
    {
        $id = $permission instanceof Permission
            ? $permission->id
            : Permission::query()->where('name', $permission)->value('id');

        if ($id) {
            $this->directPermissions()->syncWithoutDetaching([$id]);
        }
    }

    public function revokePermissionTo(string|Permission $permission): void
    {
        $id = $permission instanceof Permission
            ? $permission->id
            : Permission::query()->where('name', $permission)->value('id');

        if ($id) {
            $this->directPermissions()->detach($id);
        }
    }

    protected function resolveRoleId(Role|string $role): string
    {
        if ($role instanceof Role) {
            return $role->id;
        }

        return Role::query()->where('name', $role)->firstOrFail()->id;
    }

    /** @return list<string> */
    public function effectivePermissionNames(): array
    {
        $fromRole = collect();

        if ($this->role_id) {
            $role = $this->relationLoaded('role') ? $this->role : $this->role()->first();
            if ($role) {
                $fromRole = $role->relationLoaded('permissions')
                    ? $role->permissions->pluck('name')
                    : $role->permissions()->pluck('name');
            }
        }

        $fromDirect = $this->relationLoaded('directPermissions')
            ? $this->directPermissions->pluck('name')
            : $this->directPermissions()->pluck('name');

        return $fromRole->merge($fromDirect)->unique()->sort()->values()->all();
    }
}
