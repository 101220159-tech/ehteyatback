<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'customer_id'      => User::factory(),
            'provider_id'      => Provider::factory(),
            'service_id'       => Service::factory(),
            'scheduled_at'     => now()->addDays(2)->setTime(10, 0),
            'duration_minutes' => 60,
            'status'           => 'pending',
            'customer_address' => fake()->streetAddress(),
            'customer_notes'   => fake()->optional()->sentence(),
        ];
    }
}
