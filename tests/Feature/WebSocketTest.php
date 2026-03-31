<?php

namespace Tests\Feature;

use App\Events\ChatTyping;
use App\Models\Chat;
use App\Models\Provider;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WebSocketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_typing_indicator_broadcasts(): void
    {
        Event::fake();

        $customer = User::factory()->create(['email_verified_at' => now()]);
        $customer->assignRole('customer');

        $providerUser = User::factory()->create(['email_verified_at' => now()]);
        $providerUser->assignRole('provider');

        $provider = Provider::query()->create([
            'user_id' => $providerUser->id,
            'bio' => 'P',
            'experience_years' => 1,
            'rating_avg' => 5,
        ]);

        $chat = Chat::query()->create([
            'client_id' => $customer->id,
            'provider_id' => $provider->id,
        ]);

        $token = $customer->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/customer/chats/{$chat->id}/typing", [
                'typing' => true,
            ])
            ->assertOk();

        Event::assertDispatched(ChatTyping::class);
    }
}
