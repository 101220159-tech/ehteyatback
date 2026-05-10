<?php

namespace Tests\Performance;

use App\Models\User;
use App\Services\ChatbotService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Lightweight timing checks for the chatbot HTTP path (ChatbotService is mocked for stability).
 * For true multi-process load testing (10+ parallel clients), use an external tool (k6, Locust, Artillery)
 * against a running staging API; this class guards regressions in baseline latency.
 */
class ChatbotLoadTest extends TestCase
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

        $this->mock(ChatbotService::class, function ($mock) {
            $mock->shouldReceive('processMessage')
                ->byDefault()
                ->andReturn([
                    'response' => 'Load-test stub reply.',
                    'recommendations' => [],
                    'google_places' => [],
                    'intent' => [
                        'service_type' => 'cleaning',
                        'detected_services' => ['cleaning'],
                        'is_urgent' => false,
                        'price_preference' => 'any',
                        'location' => null,
                        'confidence' => 0.9,
                    ],
                ]);
        });
    }

    protected function customerToken(): string
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('customer');

        return $user->createToken('load')->plainTextToken;
    }

    public function test_first_message_completes_within_ten_seconds(): void
    {
        $token = $this->customerToken();

        $start = microtime(true);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/chatbot/message', [
                'message' => 'Need housekeeping in Hamra tomorrow',
            ])
            ->assertOk();
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(10.0, $elapsed, 'First chatbot message should complete in under 10s (mocked service).');
    }

    public function test_subsequent_messages_complete_within_three_seconds(): void
    {
        $token = $this->customerToken();

        $first = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/chatbot/message', [
                'message' => 'Need housekeeping in Hamra tomorrow',
            ]);
        $first->assertOk();
        $conversationId = $first->json('data.conversation_id');

        $start = microtime(true);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/chatbot/message', [
                'message' => 'Actually make it deep cleaning please',
                'conversation_id' => $conversationId,
            ])
            ->assertOk();
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(3.0, $elapsed, 'Follow-up message should complete in under 3s (mocked service).');
    }

    public function test_burst_of_ten_messages_stays_under_aggregate_budget(): void
    {
        $token = $this->customerToken();

        $first = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/chatbot/message', [
                'message' => 'Initial burst seed message for queue handling',
            ]);
        $first->assertOk();
        $conversationId = $first->json('data.conversation_id');

        $wall = microtime(true);
        for ($i = 0; $i < 10; $i++) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/v1/customer/chatbot/message', [
                    'message' => "Burst follow-up message number {$i} with enough chars",
                    'conversation_id' => $conversationId,
                ])
                ->assertOk();
        }
        $total = microtime(true) - $wall;

        $this->assertLessThan(30.0, $total, 'Ten sequential chatbot turns should finish within 30s aggregate (mocked LLM).');
    }
}
