<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\ProviderService;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\ChatbotService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChatbotFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * Simulates: login → open chat (list conversations) → send message → receive reply with provider
     * → "click provider" (use recommendation id) → create booking.
     */
    public function test_login_chat_message_recommendation_and_booking_flow(): void
    {
        $category = ServiceCategory::query()->create([
            'name' => 'Plumbing',
            'description' => 'Test',
        ]);
        $service = Service::query()->create([
            'category_id' => $category->id,
            'name' => 'Leak Repair',
            'description' => 'Test',
            'base_price' => 50.00,
        ]);

        $providerUser = User::factory()->create(['email_verified_at' => now()]);
        $providerUser->assignRole('provider');

        $provider = Provider::query()->create([
            'user_id' => $providerUser->id,
            'rating_avg' => 4.50,
            'is_active' => true,
            'is_verified' => true,
        ]);

        ProviderService::query()->create([
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'price' => 75.00,
        ]);

        $customer = User::factory()->create([
            'email' => 'flow-customer@example.test',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $customer->assignRole('customer');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'flow-customer@example.test',
            'password' => 'password',
        ]);
        $login->assertOk();
        $token = $login->json('token');
        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/customer/chatbot/conversations')
            ->assertOk()
            ->assertJsonPath('success', true);

        $scheduledAt = Carbon::now()->addDays(2)->setTime(15, 0, 0)->toIso8601String();

        $this->mock(ChatbotService::class, function ($mock) use ($provider, $service) {
            $mock->shouldReceive('processMessage')
                ->once()
                ->andReturn([
                    'response' => 'I found a NexVex plumber for you.',
                    'recommendations' => [
                        [
                            'source' => 'nexvex',
                            'id' => $provider->id,
                            'name' => 'Test Provider',
                            'platform_rating' => 4.5,
                            'platform_review_count' => 3,
                            'google_rating' => null,
                            'google_review_count' => null,
                            'location' => 'Beirut',
                        ],
                    ],
                    'google_places' => [],
                    'intent' => [
                        'service_type' => 'plumbing',
                        'detected_services' => ['plumbing'],
                        'is_urgent' => false,
                        'price_preference' => 'any',
                        'location' => 'Beirut',
                        'confidence' => 0.9,
                    ],
                ]);
        });

        $chat = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/chatbot/message', [
                'message' => 'I need an urgent plumber in Beirut for a leak',
            ]);

        $chat->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent.service_type', 'plumbing');

        $recs = $chat->json('data.recommendations');
        $this->assertIsArray($recs);
        $this->assertNotEmpty($recs);
        $this->assertSame($provider->id, $recs[0]['id']);

        $booking = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/bookings', [
                'provider_id' => $provider->id,
                'service_id' => $service->id,
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => 60,
            ]);

        $booking->assertCreated()
            ->assertJsonPath('data.provider_id', $provider->id)
            ->assertJsonPath('data.service_id', $service->id)
            ->assertJsonPath('data.status', 'pending');
    }
}
