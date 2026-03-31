<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_login_returns_token_for_verified_user(): void
    {
        $user = User::factory()->create([
            'email' => 'u@test.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('customer');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'u@test.com',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_unverified_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'x@test.com',
            'password' => 'password',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'x@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
    }

    public function test_customer_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('customer');
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/dashboard/stats')
            ->assertStatus(403);
    }

    public function test_admin_can_access_admin_dashboard_stats(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('admin');
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure([
                'users_count',
                'providers_count',
                'services_count',
                'bookings_pending',
                'completed_bookings_count',
            ]);
    }

    public function test_public_services_endpoint_is_reachable(): void
    {
        $this->getJson('/api/v1/services')->assertOk();
    }
}
