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

    public function assignRole(Role|string|int $role, ?int $assignedBy = null): void
    {
        $this->update(['role_id' => $this->resolveRoleId($role)]);
    }

    public function syncRoles(array $roleIds, ?int $assignedBy = null): void
    {
        if ($roleIds === []) {
            return;
        }
        $this->update(['role_id' => (int) reset($roleIds)]);
    }

    public function hasRole(string|array $roles): bool
    {
        $names = is_array($roles) ? $roles : [$roles];
        $role = $this->relationLoaded('role') ? $this->role : $this->role()->first();
        if (! $role) {
            return false;
        }

        return in_array($role->name, $names, true);
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

    /** @param  list<string>  $names */
    public function hasAnyPermission(array $names): bool
    {
        foreach ($names as $name) {
            if ($this->hasSinglePermission($name)) {
                return true;
            }
        }

        return false;
    }

    public function hasRoleOrPermission(array $roles, array $permissions): bool
    {
        return $this->hasRole($roles) || $this->hasPermission($permissions);
    }

    protected function hasSinglePermission(string $name): bool
    {
        if ($this->directPermissions()->where('permissions.name', $name)->exists()) {
            return true;
        }

        $role = $this->relationLoaded('role') ? $this->role : $this->role()->first();
        if (! $role) {
            return false;
        }

        return $role->permissions()->where('permissions.name', $name)->exists();
    }

    public function givePermissionTo(string|Permission $permission, ?int $grantedBy = null, $expiresAt = null): void
    {
        $id = $permission instanceof Permission ? $permission->id : Permission::query()->where('name', $permission)->value('id');
        if ($id) {
            $this->directPermissions()->syncWithoutDetaching([$id]);
        }
    }

    public function denyPermissionTo(string|Permission $permission, ?int $grantedBy = null, $expiresAt = null): void
    {
        $id = $permission instanceof Permission ? $permission->id : Permission::query()->where('name', $permission)->value('id');
        if ($id) {
            $this->directPermissions()->detach($id);
        }
    }

    public function revokePermissionTo(string|Permission $permission): void
    {
        $this->denyPermissionTo($permission);
    }

    protected function resolveRoleId(Role|string|int $role): int
    {
        if ($role instanceof Role) {
            return $role->id;
        }
        if (is_int($role) || (is_string($role) && ctype_digit($role))) {
            return (int) $role;
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
