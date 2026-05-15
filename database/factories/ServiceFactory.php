<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'category_id' => ServiceCategory::factory(),
            'name'        => fake()->words(3, true),
            'description' => fake()->sentence(),
            'base_price'  => fake()->randomFloat(2, 20, 500),
        ];
    }
}
