<?php

namespace Tests\Feature;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\User;
use App\Services\ChatbotService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function mockChatbotProcess(array $payload): void
    {
        $this->mock(ChatbotService::class, function ($mock) use ($payload) {
            $mock->shouldReceive('processMessage')
                ->zeroOrMoreTimes()
                ->andReturn($payload);
        });
    }

    protected function customerWithToken(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('customer');
        $token = $user->createToken('test')->plainTextToken;

        return [$user, $token];
    }

    public function test_authentication_required(): void
    {
        $this->postJson('/api/v1/customer/chatbot/message', [
            'message' => 'I need a plumber in Beirut',
        ])->assertUnauthorized();
    }

    public function test_provider_role_cannot_use_customer_chatbot(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('provider');
        $token = $user->createToken('test')->plainTextToken;

        $this->mockChatbotProcess([
            'response' => 'should not run',
            'recommendations' => [],
            'google_places' => [],
            'intent' => ['service_type' => 'general'],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/chatbot/message', [
                'message' => 'I need a plumber in Beirut',
            ])
            ->assertForbidden();
    }

    public function test_valid_message_returns_successful_response(): void
    {
        [, $token] = $this->customerWithToken();

        $this->mockChatbotProcess([
            'response' => 'Here are plumbers near you.',
            'recommendations' => [],
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

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/chatbot/message', [
                'message' => 'I need a plumber in Beirut',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', 'Here are plumbers near you.')
            ->assertJsonPath('data.intent.service_type', 'plumbing')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'message',
                    'recommendations',
                    'google_places',
                    'intent',
                    'conversation_id',
                    'message_id',
                ],
            ]);
    }

    public function test_invalid_conversation_uuid_format_returns_validation_error(): void
    {
        [, $token] = $this->customerWithToken();

        $this->mockChatbotProcess([
            'response' => 'x',
            'recommendations' => [],
            'google_places' => [],
            'intent' => ['service_type' => 'general'],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/chatbot/message', [
                'message' => 'Hello again',
                'conversation_id' => 'not-a-valid-uuid',
            ])
            ->assertStatus(422);
    }

    public function test_nonexistent_conversation_id_returns_validation_error(): void
    {
        [, $token] = $this->customerWithToken();

        $this->mockChatbotProcess([
            'response' => 'x',
            'recommendations' => [],
            'google_places' => [],
            'intent' => ['service_type' => 'general'],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/chatbot/message', [
                'message' => 'Hello again',
                'conversation_id' => (string) Str::uuid(),
            ])
            ->assertStatus(422);
    }

    public function test_conversation_belonging_to_another_user_returns_not_found(): void
    {
        [$owner] = $this->customerWithToken();
        $conversation = ChatbotConversation::create([
            'user_id' => $owner->id,
            'status' => 'active',
        ]);

        [, $token] = $this->customerWithToken();

        $this->mockChatbotProcess([
            'response' => 'x',
            'recommendations' => [],
            'google_places' => [],
            'intent' => ['service_type' => 'general'],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/chatbot/message', [
                'message' => 'Trying to hijack a thread',
                'conversation_id' => $conversation->id,
            ])
            ->assertNotFound();
    }

    public function test_conversation_id_persistence_across_messages(): void
    {
        [, $token] = $this->customerWithToken();

        $this->mock(ChatbotService::class, function ($mock) {
            $mock->shouldReceive('processMessage')
                ->twice()
                ->andReturn(
                    [
                        'response' => 'First reply',
                        'recommendations' => [],
                        'google_places' => [],
                        'intent' => ['service_type' => 'plumbing', 'detected_services' => ['plumbing'], 'is_urgent' => false, 'price_preference' => 'any', 'location' => null, 'confidence' => 0.9],
                    ],
                    [
                        'response' => 'Second reply',
                        'recommendations' => [],
                        'google_places' => [],
                        'intent' => ['service_type' => 'plumbing', 'detected_services' => ['plumbing'], 'is_urgent' => false, 'price_preference' => 'any', 'location' => 'Beirut', 'confidence' => 0.9],
                    ],
                );
        });

        $first = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/chatbot/message', [
                'message' => 'I need a plumber',
            ]);

        $first->assertOk();
        $conversationId = $first->json('data.conversation_id');
        $this->assertNotEmpty($conversationId);

        $second = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/chatbot/message', [
                'message' => 'They should come to Beirut',
                'conversation_id' => $conversationId,
            ]);

        $second->assertOk()
            ->assertJsonPath('data.conversation_id', $conversationId)
            ->assertJsonPath('data.message', 'Second reply');

        $messageCount = ChatbotMessage::query()
            ->where('conversation_id', $conversationId)
            ->count();
        $this->assertSame(4, $messageCount, 'Expected two user turns and two bot replies in one conversation');
    }

    public function test_empty_message_returns_validation_error(): void
    {
        [, $token] = $this->customerWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/chatbot/message', [
                'message' => '',
            ])
            ->assertStatus(422);
    }

    public function test_too_short_message_returns_validation_error(): void
    {
        [, $token] = $this->customerWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/chatbot/message', [
                'message' => 'a',
            ])
            ->assertStatus(422);
    }

    public function test_long_message_over_limit_returns_validation_error(): void
    {
        [, $token] = $this->customerWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/chatbot/message', [
                'message' => str_repeat('x', 1001),
            ])
            ->assertStatus(422);
    }

    public function test_max_length_message_is_accepted(): void
    {
        [, $token] = $this->customerWithToken();

        $body = str_repeat('x', 1000);

        $this->mockChatbotProcess([
            'response' => 'Acknowledged.',
            'recommendations' => [],
            'google_places' => [],
            'intent' => ['service_type' => 'general', 'detected_services' => [], 'is_urgent' => false, 'price_preference' => 'any', 'location' => null, 'confidence' => 0.35],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/chatbot/message', [
                'message' => $body,
            ])
            ->assertOk();
    }
}
