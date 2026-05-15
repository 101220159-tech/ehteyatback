<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Provider;
use App\Models\ProviderSchedule;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function createProviderUser(): array
    {
        $role = Role::where('name', 'provider')->first();
        $user = User::factory()->create([
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);
        $provider = Provider::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $token    = $user->createToken('test')->plainTextToken;

        return compact('user', 'provider', 'token');
    }

    public function test_accept_creates_schedule_entry(): void
    {
        ['provider' => $provider, 'token' => $token] = $this->createProviderUser();
        $customer = User::factory()->create();
        $service  = Service::factory()->create();

        $scheduledAt = Carbon::now()->addDays(3)->setTime(10, 0);

        $booking = Booking::factory()->create([
            'customer_id'      => $customer->id,
            'provider_id'      => $provider->id,
            'service_id'       => $service->id,
            'scheduled_at'     => $scheduledAt,
            'duration_minutes' => 60,
            'status'           => 'pending',
        ]);

        $response = $this->withToken($token)
            ->postJson("/api/v1/provider/booking/{$booking->id}/accept");

        $response->assertOk()
            ->assertJsonPath('schedule.status', 'accepted')
            ->assertJsonPath('schedule.booking_id', $booking->id);

        $this->assertDatabaseHas('provider_schedules', [
            'booking_id'  => $booking->id,
            'provider_id' => $provider->id,
            'status'      => 'accepted',
        ]);

        $this->assertSame('accepted', $booking->fresh()->status);
    }

    public function test_accept_rejects_conflicting_slot(): void
    {
        ['provider' => $provider, 'token' => $token] = $this->createProviderUser();
        $customer = User::factory()->create();
        $service  = Service::factory()->create();

        $scheduledAt = Carbon::now()->addDays(4)->setTime(14, 0);

        $existing = Booking::factory()->create([
            'customer_id'      => $customer->id,
            'provider_id'      => $provider->id,
            'service_id'       => $service->id,
            'scheduled_at'     => $scheduledAt,
            'duration_minutes' => 60,
            'status'           => 'accepted',
        ]);

        ProviderSchedule::create([
            'provider_id'      => $provider->id,
            'booking_id'       => $existing->id,
            'scheduled_date'   => $scheduledAt->toDateString(),
            'scheduled_time'   => $scheduledAt->format('H:i:s'),
            'duration_minutes' => 60,
            'status'           => 'accepted',
        ]);

        $pending = Booking::factory()->create([
            'customer_id'      => User::factory()->create()->id,
            'provider_id'      => $provider->id,
            'service_id'       => $service->id,
            'scheduled_at'     => $scheduledAt->copy()->addMinutes(30),
            'duration_minutes' => 60,
            'status'           => 'pending',
        ]);

        $this->withToken($token)
            ->postJson("/api/v1/provider/booking/{$pending->id}/accept")
            ->assertStatus(422);
    }

    public function test_schedule_list_returns_entries(): void
    {
        ['provider' => $provider, 'token' => $token] = $this->createProviderUser();
        $customer = User::factory()->create(['name' => 'Jane Customer']);
        $service  = Service::factory()->create(['name' => 'Plumbing']);
        $at       = Carbon::now()->addDays(2)->setTime(9, 0);

        $booking = Booking::factory()->create([
            'customer_id'      => $customer->id,
            'provider_id'      => $provider->id,
            'service_id'       => $service->id,
            'scheduled_at'     => $at,
            'status'           => 'accepted',
            'customer_address' => '123 Main St',
            'customer_notes'   => 'Ring the bell',
        ]);

        ProviderSchedule::create([
            'provider_id'      => $provider->id,
            'booking_id'       => $booking->id,
            'scheduled_date'   => $at->toDateString(),
            'scheduled_time'   => $at->format('H:i:s'),
            'duration_minutes' => 60,
            'status'           => 'accepted',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/provider/schedule?date='.$at->toDateString().'&search=Jane')
            ->assertOk()
            ->assertJsonPath('data.0.customer_name', 'Jane Customer')
            ->assertJsonPath('data.0.service_name', 'Plumbing')
            ->assertJsonPath('data.0.address', '123 Main St');
    }
}
