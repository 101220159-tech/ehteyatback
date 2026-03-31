<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    public function run(): void
    {
        ServiceCategory::query()->firstOrCreate(
            ['name' => 'Plumbing'],
            ['description' => 'Water, pipes, leaks']
        );

        ServiceCategory::query()->firstOrCreate(
            ['name' => 'Electrical'],
            ['description' => 'Wiring, outlets, safety']
        );

        ServiceCategory::query()->firstOrCreate(
            ['name' => 'IT Support'],
            ['description' => 'Computers and networks']
        );
    }
}
