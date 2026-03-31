<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $roleId = Role::query()->where('name', 'super_admin')->value('id');
        if (! $roleId) {
            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => 'admin@sp-platform.test'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'email_verified_at' => now(),
                'role_id' => $roleId,
            ]
        );

        $user->assignRole('super_admin');
    }
}
