<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authenticated_user_can_save_fcm_token(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('customer');
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/user/fcm-token', [
                'fcm_token' => 'test-fcm-device-token',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'FCM token saved.');

        $this->assertSame('test-fcm-device-token', $user->fresh()->fcm_token);
    }
}
