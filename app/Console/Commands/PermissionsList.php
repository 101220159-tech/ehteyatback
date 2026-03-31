<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PermissionsList extends Command
{
    protected $signature = 'permissions:list
                            {--role= : Filter permissions by role name}
                            {--group : Group permissions by module}
                            {--json : Output as JSON}';

    protected $description = 'List all permissions in the system with their roles';

    public function handle()
    {
        // Check database connection
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $this->error('Database connection failed!');
            $this->error('Please start MySQL in XAMPP first');
            return 1;
        }

        // Get permissions
        $permissions = Permission::with('roles')->get();
        
        if ($permissions->isEmpty()) {
            $this->error('No permissions found. Run the seeder first:');
            $this->info('php artisan db:seed --class=RolePermissionSeeder');
            return 1;
        }

        // Filter by role if specified
        if ($roleName = $this->option('role')) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) {
                $this->error("Role '{$roleName}' not found.");
                $this->info("Available roles: " . Role::pluck('name')->implode(', '));
                return 1;
            }
            $permissions = $permissions->filter(
                fn ($permission) => $permission->roles->contains(fn ($r) => (int) $r->id === (int) $role->id)
            );
            $this->info("Showing permissions for role: {$roleName}\n");
        }

        // Output as JSON
        if ($this->option('json')) {
            $this->outputJson($permissions);
            return 0;
        }

        // Group by module or display as list
        if ($this->option('group')) {
            $this->displayGrouped($permissions);
        } else {
            $this->displayList($permissions);
        }

        return 0;
    }

    protected function displayList($permissions)
    {
        $headers = ['ID', 'Permission Name', 'Description', 'Roles'];
        $rows = [];

        foreach ($permissions as $permission) {
            $roleNames = $permission->roles->pluck('name')->implode(', ');
            $rows[] = [
                $permission->id,
                $permission->name,
                $permission->description ?? '—',
                $roleNames ?: '—'
            ];
        }

        $this->table($headers, $rows);
        $this->info("\nTotal: " . $permissions->count() . " permissions");
    }

    protected function displayGrouped($permissions)
    {
        $groups = [];

        foreach ($permissions as $permission) {
            // Determine module from permission name
            $name = $permission->name;
            $module = 'Other';
            
            if (str_contains($name, 'user')) $module = 'Users';
            elseif (str_contains($name, 'provider')) $module = 'Providers';
            elseif (str_contains($name, 'booking')) $module = 'Bookings';
            elseif (str_contains($name, 'review')) $module = 'Reviews';
            elseif (str_contains($name, 'message')) $module = 'Messages';
            elseif (str_contains($name, 'service')) $module = 'Services';
            elseif (str_contains($name, 'admin')) $module = 'Admin';
            elseif (str_contains($name, 'role')) $module = 'Roles';
            elseif (str_contains($name, 'zone')) $module = 'Zones';
            elseif (str_contains($name, 'payment')) $module = 'Payments';
            elseif (str_contains($name, 'report')) $module = 'Reports';
            
            $groups[$module][] = $permission;
        }

        // Sort groups alphabetically
        ksort($groups);

        foreach ($groups as $module => $items) {
            $this->info("\n=== " . strtoupper($module) . " ===");
            $rows = [];
            foreach ($items as $p) {
                $rows[] = [
                    $p->name,
                    $p->description ?? '—',
                    $p->roles->pluck('name')->implode(', ') ?: '—'
                ];
            }
            $this->table(['Permission', 'Description', 'Roles'], $rows);
        }
        
        $this->info("\n📊 Total permissions: " . $permissions->count());
        $this->info("📁 Total modules: " . count($groups));
    }

    protected function outputJson($permissions)
    {
        $data = $permissions->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'description' => $permission->description,
                'roles' => $permission->roles->pluck('name')
            ];
        });

        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }
}
