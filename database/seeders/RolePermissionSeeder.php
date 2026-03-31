<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'users' => [
                ['view_users', 'View users'],
                ['create_users', 'Create users'],
                ['edit_users', 'Edit users'],
                ['delete_users', 'Delete users'],
                ['manage_roles', 'Manage roles'],
                ['manage_permissions', 'Manage permissions'],
            ],
            'providers' => [
                ['view_providers', 'View providers'],
                ['manage_providers', 'Manage providers'],
                ['view_provider_dashboard', 'View provider dashboard'],
                ['manage_own_profile', 'Manage own provider profile'],
                ['manage_services', 'Manage services'],
                ['manage_own_bookings', 'Manage own bookings'],
                ['manage_portfolio', 'Manage portfolio / projects'],
                ['view_own_reviews', 'View own reviews'],
                ['respond_to_reviews', 'Respond to customer reviews'],
                ['manage_own_availability', 'Manage own availability'],
                ['view_own_earnings', 'View own earnings and payouts'],
            ],
            'customers' => [
                ['view_customer_dashboard', 'View customer dashboard'],
                ['create_bookings', 'Create bookings'],
                ['cancel_own_bookings', 'Cancel own bookings'],
                ['view_own_bookings', 'View own bookings'],
                ['create_reviews', 'Create reviews'],
                ['edit_own_reviews', 'Edit own reviews'],
                ['write_reviews', 'Write / submit reviews (dashboard; use with create/edit APIs)'],
                ['message_providers', 'Message providers'],
                ['view_provider_profiles', 'View provider profiles'],
                ['manage_own_profile', 'Manage own profile'],
                ['manage_own_addresses', 'Manage own addresses'],
                ['view_own_history', 'View own history'],
            ],
            'bookings' => [
                ['view_all_bookings', 'View all bookings'],
                ['manage_all_bookings', 'Manage all bookings'],
                ['assign_bookings', 'Assign bookings'],
                ['modify_booking_status', 'Modify booking status'],
            ],
            'chats' => [
                ['send_messages', 'Send messages'],
                ['view_conversations', 'View conversations'],
                ['delete_own_messages', 'Delete own messages'],
                ['moderate_chats', 'Moderate chats'],
            ],
            'admin' => [
                ['view_admin_dashboard', 'View admin dashboard'],
                ['manage_users', 'Manage users'],
                ['verify_providers', 'Verify and approve providers'],
                ['manage_services_catalog', 'Manage global services catalog (admin)'],
                ['manage_zones', 'Manage geographic zones / coverage'],
                ['view_payments', 'View payments and billing'],
                ['view_reports', 'View analytics and reports'],
                ['view_audit_logs', 'View audit logs'],
                ['send_system_notifications', 'Send system notifications'],
            ],
        ];

        $permissionModels = [];
        foreach ($definitions as $items) {
            foreach ($items as [$name, $display]) {
                $permissionModels[$name] = Permission::query()->firstOrCreate(
                    ['name' => $name],
                    ['description' => $display]
                );
            }
        }

        $allIds = collect($permissionModels)->pluck('id')->all();

        $superAdmin = Role::query()->firstOrCreate(
            ['name' => 'super_admin'],
            ['description' => 'Full system access']
        );
        $superAdmin->permissions()->sync($allIds);

        $admin = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Administrative access']
        );
        $admin->permissions()->sync($allIds);

        $provider = Role::query()->firstOrCreate(
            ['name' => 'provider'],
            ['description' => 'Service provider']
        );
        $providerNames = [
            'view_provider_dashboard', 'manage_own_profile', 'manage_services', 'manage_own_bookings',
            'manage_portfolio', 'view_own_reviews', 'respond_to_reviews', 'manage_own_availability',
            'view_own_earnings',
            'send_messages', 'view_conversations',
        ];
        $provider->permissions()->sync(collect($providerNames)->map(fn ($n) => $permissionModels[$n]->id)->all());

        $customer = Role::query()->firstOrCreate(
            ['name' => 'customer'],
            ['description' => 'Customer']
        );
        $customerNames = [
            'view_customer_dashboard', 'create_bookings', 'cancel_own_bookings', 'view_own_bookings',
            'create_reviews', 'edit_own_reviews', 'write_reviews', 'message_providers', 'view_provider_profiles',
            'manage_own_profile', 'manage_own_addresses', 'view_own_history',
            'send_messages', 'view_conversations',
        ];
        $customer->permissions()->sync(collect($customerNames)->map(fn ($n) => $permissionModels[$n]->id)->all());
    }
}
