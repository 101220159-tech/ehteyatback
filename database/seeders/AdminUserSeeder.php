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

        $email = (string) env('ADMIN_EMAIL', 'admin@sp-platform.test');
        $password = (string) env('ADMIN_PASSWORD', 'Admin@1234');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name'              => (string) env('ADMIN_NAME', 'Super Admin'),
                'password'          => $password,
                'email_verified_at' => now(),
                'role_id'           => $roleId,
            ]
        );
    }
}
