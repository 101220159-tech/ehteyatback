<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiPermissionsEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_auth_permissions_returns_role_and_permission_list(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('customer');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/permissions');

        $response->assertOk()
            ->assertJsonStructure(['role_name', 'permissions'])
            ->assertJson(['role_name' => 'customer']);

        $names = $response->json('permissions');
        $this->assertIsArray($names);
        $this->assertContains('create_bookings', $names);
    }

    public function test_test_permissions_includes_example_checks(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('provider');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/test/permissions');

        $response->assertOk()
            ->assertJsonPath('role_name', 'provider')
            ->assertJsonStructure([
                'example_checks' => [
                    'is_customer',
                    'is_provider',
                    'is_admin',
                    'can_create_booking',
                    'can_manage_services',
                    'can_manage_users',
                ],
            ])
            ->assertJsonPath('example_checks.is_provider', true)
            ->assertJsonPath('example_checks.is_customer', false);
    }
}
