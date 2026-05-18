<?php

namespace Tests\Feature;

use App\Models\EmergencyPlaceFavorite;
use App\Models\EmergencyPlaceHistory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerEmergencyPlaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function customerToken(): string
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('customer');

        return $user->createToken('test')->plainTextToken;
    }

    public function test_customer_can_record_and_list_emergency_history(): void
    {
        $token = $this->customerToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/emergency/history', [
                'place_id' => 'ChIJtest123',
                'name' => 'City Hospital',
                'address' => 'Main St',
                'latitude' => 33.89,
                'longitude' => 35.51,
                'place_type' => 'hospital',
                'distance_km' => 1.2,
                'action' => 'directions',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'City Hospital');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/customer/emergency/history')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseCount('emergency_place_histories', 1);
    }

    public function test_customer_can_manage_emergency_favorites(): void
    {
        $token = $this->customerToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/emergency/favorites', [
                'place_id' => 'ChIJpharm456',
                'name' => 'Quick Pharmacy',
                'latitude' => 33.9,
                'longitude' => 35.52,
                'place_type' => 'pharmacy',
                'phone' => '+96111223344',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Quick Pharmacy');

        $favorite = EmergencyPlaceFavorite::query()->first();
        $this->assertNotNull($favorite);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/customer/emergency/favorites')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/customer/emergency/favorites/'.$favorite->id)
            ->assertOk();

        $this->assertDatabaseCount('emergency_place_favorites', 0);
    }

    public function test_guest_cannot_access_emergency_endpoints(): void
    {
        $this->getJson('/api/v1/customer/emergency/history')->assertUnauthorized();
    }

    public function test_customer_can_search_nearby_emergency_places(): void
    {
        Config::set('services.google_maps.key', 'test-key');
        Http::fake([
            'maps.googleapis.com/maps/api/place/nearbysearch/*' => Http::response([
                'status' => 'OK',
                'results' => [
                    [
                        'place_id' => 'ChIJhospital',
                        'name' => 'Test Hospital',
                        'vicinity' => 'Beirut',
                        'geometry' => ['location' => ['lat' => 33.89, 'lng' => 35.51]],
                        'opening_hours' => ['open_now' => true],
                    ],
                ],
            ]),
        ]);

        $token = $this->customerToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/customer/emergency/nearby?'.http_build_query([
                'latitude' => 33.88,
                'longitude' => 35.50,
                'type' => 'hospital',
            ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'Test Hospital')
            ->assertJsonPath('data.0.open_status', 'open');
    }
}
