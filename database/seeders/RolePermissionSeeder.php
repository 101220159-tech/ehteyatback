<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ── Permission definitions ────────────────────────────────────────
        $definitions = [
            // Users
            ['view_users',             'View users'],
            ['create_users',           'Create users'],
            ['edit_users',             'Edit users'],
            ['delete_users',           'Delete users'],
            ['manage_roles',           'Manage roles'],
            ['manage_permissions',     'Manage permissions'],

            // Providers (admin side)
            ['view_providers',         'View providers'],
            ['manage_providers',       'Manage providers'],
            ['verify_providers',       'Verify and approve providers'],
            ['assign_provider_zone',   'Assign provider to a zone'],
            ['manage_provider_chat_flag', 'Toggle allow_chat flag on providers'],
            ['manage_provider_vip',    'Grant / revoke VIP status'],
            ['manage_subscriptions',   'Record and manage provider subscriptions'],

            // Provider self-management
            ['view_provider_dashboard','View provider dashboard'],
            ['manage_own_profile',     'Manage own provider profile'],
            ['manage_own_services',    'Manage own service offerings'],
            ['manage_own_bookings',    'Manage own bookings'],
            ['manage_portfolio',       'Manage portfolio / projects'],
            ['manage_certifications',  'Manage own certifications'],
            ['manage_documents',       'Manage own official documents'],
            ['view_own_reviews',       'View own reviews'],
            ['manage_own_availability','Manage own availability'],
            ['view_own_earnings',      'View own earnings'],
            ['toggle_own_status',      'Toggle own active/inactive status'],

            // Customer
            ['view_customer_dashboard','View customer dashboard'],
            ['create_bookings',        'Create bookings'],
            ['cancel_own_bookings',    'Cancel own bookings'],
            ['view_own_bookings',      'View own bookings'],
            ['create_reviews',         'Create reviews'],
            ['edit_own_reviews',       'Edit own reviews'],
            ['manage_own_profile',     'Manage own profile'],
            ['manage_own_addresses',   'Manage own addresses'],
            ['view_own_history',       'View own booking history'],
            ['view_provider_profiles', 'View provider public profiles'],

            // Chat
            ['send_messages',          'Send chat messages'],
            ['view_conversations',     'View chat conversations'],

            // Bookings (admin)
            ['view_all_bookings',      'View all bookings'],
            ['manage_all_bookings',    'Manage all bookings'],

            // Services / categories (admin)
            ['manage_services',        'Manage global services catalog'],
            ['manage_categories',      'Manage service categories'],

            // Zones / areas (admin)
            ['manage_zones',           'Manage zones and areas'],

            // Admin dashboard / reports
            ['view_admin_dashboard',   'View admin dashboard'],
            ['view_reports',           'View analytics and reports'],
            ['send_system_notifications', 'Send system notifications'],
            ['view_payments',          'View payment records'],
        ];

        // Create / update all permissions
        $map = [];
        foreach ($definitions as [$name, $desc]) {
            $map[$name] = Permission::query()->updateOrCreate(
                ['name' => $name],
                ['description' => $desc]
            );
        }

        // ── Roles ─────────────────────────────────────────────────────────

        // super_admin — full access
        $superAdmin = Role::query()->firstOrCreate(
            ['name' => 'super_admin'],
            ['description' => 'Full system access']
        );
        $superAdmin->permissions()->sync(collect($map)->pluck('id')->all());

        // admin — same as super_admin for now (can be scoped later)
        $admin = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Administrative access']
        );
        $admin->permissions()->sync(collect($map)->pluck('id')->all());

        // provider
        $provider = Role::query()->firstOrCreate(
            ['name' => 'provider'],
            ['description' => 'Service provider']
        );
        $providerPerms = [
            'view_provider_dashboard', 'manage_own_profile', 'manage_own_services',
            'manage_own_bookings', 'manage_portfolio', 'manage_certifications',
            'manage_documents', 'view_own_reviews', 'manage_own_availability',
            'view_own_earnings', 'toggle_own_status',
            'send_messages', 'view_conversations',
        ];
        $provider->permissions()->sync(
            collect($providerPerms)->map(fn ($n) => $map[$n]->id)->all()
        );

        // customer
        $customer = Role::query()->firstOrCreate(
            ['name' => 'customer'],
            ['description' => 'End customer']
        );
        $customerPerms = [
            'view_customer_dashboard', 'create_bookings', 'cancel_own_bookings',
            'view_own_bookings', 'create_reviews', 'edit_own_reviews',
            'manage_own_profile', 'manage_own_addresses', 'view_own_history',
            'view_provider_profiles',
            'send_messages', 'view_conversations',
        ];
        $customer->permissions()->sync(
            collect($customerPerms)->map(fn ($n) => $map[$n]->id)->all()
        );
    }
}
