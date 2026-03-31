<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_view_backup_status_json(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('admin');
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/system/backup-status')
            ->assertOk()
            ->assertJsonStructure(['disk', 'backup_directory', 'backup_count', 'latest_backup']);
    }
}
