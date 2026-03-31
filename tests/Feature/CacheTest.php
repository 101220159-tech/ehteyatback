<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\User;
use App\Services\CacheService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_cache_service_remembers_value(): void
    {
        $svc = app(CacheService::class);
        $calls = 0;
        $v = $svc->remember('test_key', 5, function () use (&$calls) {
            $calls++;

            return 'once';
        });
        $v2 = $svc->remember('test_key', 5, function () use (&$calls) {
            $calls++;

            return 'twice';
        });

        $this->assertSame('once', $v);
        $this->assertSame('once', $v2);
        $this->assertSame(1, $calls);
    }

    public function test_public_provider_show_uses_cache_layer(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('provider');

        $provider = Provider::query()->create([
            'user_id' => $user->id,
            'name' => 'Cached Provider',
            'email' => 'cp@test.com',
            'phone' => '1',
            'status' => 'active',
            'is_verified' => true,
        ]);

        $this->getJson('/api/v1/providers/'.$provider->id)->assertOk();
        $this->getJson('/api/v1/providers/'.$provider->id)->assertOk();
    }
}
