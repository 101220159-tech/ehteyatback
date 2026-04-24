<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name'        => 'Plumbing',
                'description' => 'Water, pipes, leaks, and sanitation',
                'services'    => [
                    ['name' => 'Pipe Repair',          'description' => 'Fix leaking or broken pipes',           'base_price' => 50.00],
                    ['name' => 'Drain Cleaning',        'description' => 'Unclog and clean drainage systems',     'base_price' => 40.00],
                    ['name' => 'Water Heater Install',  'description' => 'Install or replace water heaters',      'base_price' => 120.00],
                    ['name' => 'Toilet Repair',         'description' => 'Fix running or broken toilets',         'base_price' => 45.00],
                ],
            ],
            [
                'name'        => 'Electrical',
                'description' => 'Wiring, outlets, panels, and safety',
                'services'    => [
                    ['name' => 'Wiring Installation',   'description' => 'New wiring or rewiring',               'base_price' => 80.00],
                    ['name' => 'Outlet Repair',         'description' => 'Fix or replace power outlets',          'base_price' => 35.00],
                    ['name' => 'Panel Upgrade',         'description' => 'Upgrade electrical panel',              'base_price' => 200.00],
                    ['name' => 'Light Fixture Install', 'description' => 'Install ceiling lights and fans',       'base_price' => 55.00],
                ],
            ],
            [
                'name'        => 'IT Support',
                'description' => 'Computers, networks, and tech issues',
                'services'    => [
                    ['name' => 'PC Repair',             'description' => 'Diagnose and fix computer issues',      'base_price' => 60.00],
                    ['name' => 'Network Setup',         'description' => 'Configure home or office networks',     'base_price' => 75.00],
                    ['name' => 'Data Recovery',         'description' => 'Recover lost or deleted files',         'base_price' => 100.00],
                    ['name' => 'Software Install',      'description' => 'Install and configure software',        'base_price' => 30.00],
                ],
            ],
            [
                'name'        => 'Cleaning',
                'description' => 'Home and office cleaning services',
                'services'    => [
                    ['name' => 'Regular Cleaning',      'description' => 'Standard home cleaning session',        'base_price' => 50.00],
                    ['name' => 'Deep Cleaning',         'description' => 'Thorough deep clean of property',       'base_price' => 120.00],
                    ['name' => 'Window Cleaning',       'description' => 'Interior and exterior window cleaning', 'base_price' => 40.00],
                ],
            ],
            [
                'name'        => 'Carpentry',
                'description' => 'Furniture, doors, and woodwork',
                'services'    => [
                    ['name' => 'Furniture Assembly',    'description' => 'Assemble flat-pack furniture',          'base_price' => 45.00],
                    ['name' => 'Door Repair',           'description' => 'Fix or replace doors and locks',        'base_price' => 55.00],
                    ['name' => 'Custom Shelving',       'description' => 'Design and install custom shelves',     'base_price' => 90.00],
                ],
            ],
        ];

        foreach ($categories as $cat) {
            $category = ServiceCategory::query()->firstOrCreate(
                ['name' => $cat['name']],
                ['description' => $cat['description']]
            );

            foreach ($cat['services'] as $svc) {
                Service::query()->firstOrCreate(
                    ['name' => $svc['name'], 'category_id' => $category->id],
                    ['description' => $svc['description'], 'base_price' => $svc['base_price']]
                );
            }
        }
    }
}
