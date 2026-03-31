<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Provider;
use App\Models\ProviderAvailability;
use App\Models\ProviderService;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $plumbing = ServiceCategory::query()->where('name', 'Plumbing')->first();
        $electrical = ServiceCategory::query()->where('name', 'Electrical')->first();

        $catalogPlumbing = $plumbing
            ? Service::query()->firstOrCreate(
                ['name' => 'Emergency Plumbing', 'category_id' => $plumbing->id],
                ['description' => '24/7 plumbing assistance']
            )
            : null;

        $catalogElectrical = $electrical
            ? Service::query()->firstOrCreate(
                ['name' => 'Electrical Inspection', 'category_id' => $electrical->id],
                ['description' => 'Residential electrical safety checks']
            )
            : null;

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        $providerUsers = [
            ['name' => 'Provider One', 'email' => 'provider1@sp-platform.test', 'phone' => '+96170000010'],
            ['name' => 'Provider Two', 'email' => 'provider2@sp-platform.test', 'phone' => '+96170000011'],
        ];

        foreach ($providerUsers as $i => $row) {
            $user = User::factory()->create([
                'name' => $row['name'],
                'email' => $row['email'],
                'password' => 'password',
                'phone' => $row['phone'],
                'latitude' => 33.89,
                'longitude' => 35.50,
            ]);
            $user->assignRole('provider');

            $provider = Provider::query()->create([
                'user_id' => $user->id,
                'bio' => 'Licensed professional.',
                'experience_years' => 5,
                'rating_avg' => 4.5,
            ]);

            foreach ($days as $day) {
                ProviderAvailability::query()->create([
                    'provider_id' => $provider->id,
                    'day_of_week' => $day,
                    'start_time' => '08:00:00',
                    'end_time' => '20:00:00',
                    'is_available' => true,
                ]);
            }

            if ($catalogPlumbing && $i === 0) {
                ProviderService::query()->firstOrCreate(
                    ['provider_id' => $provider->id, 'service_id' => $catalogPlumbing->id],
                    ['price' => 75.00]
                );
            }
            if ($catalogElectrical && $i === 1) {
                ProviderService::query()->firstOrCreate(
                    ['provider_id' => $provider->id, 'service_id' => $catalogElectrical->id],
                    ['price' => 90.00]
                );
            }
        }

        foreach (['Customer One' => 'customer1@sp-platform.test', 'Customer Two' => 'customer2@sp-platform.test'] as $name => $email) {
            User::factory()->create(['name' => $name, 'email' => $email, 'password' => 'password'])
                ->assignRole('customer');
        }

        $p1 = Provider::query()->whereHas('user', fn ($q) => $q->where('email', 'provider1@sp-platform.test'))->first();
        $offering = $p1 ? ProviderService::query()->where('provider_id', $p1->id)->first() : null;
        $customer = User::query()->where('email', 'customer1@sp-platform.test')->first();

        if ($p1 && $offering && $customer) {
            $when = now()->addDays(3)->setTime(10, 0);
            Booking::query()->create([
                'client_id' => $customer->id,
                'provider_id' => $p1->id,
                'service_id' => $offering->service_id,
                'booking_date' => $when,
                'status' => 'confirmed',
            ]);

            Booking::query()->create([
                'client_id' => $customer->id,
                'provider_id' => $p1->id,
                'service_id' => $offering->service_id,
                'booking_date' => now()->subDays(2)->setTime(11, 0),
                'status' => 'completed',
            ]);
        }
    }
}
