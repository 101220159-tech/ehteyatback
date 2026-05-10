<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Provider;
use App\Models\ProviderService;
use App\Models\Review;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo catalog, providers, customers, bookings, and reviews aligned with the NexVex schema.
 *
 * Run after migrations (roles must exist). Safe to run standalone:
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * If roles are missing, RolePermissionSeeder is invoked automatically.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Starting NexVex Demo Data Seeder...');

        if (! Role::query()->where('name', 'provider')->exists()) {
            $this->command->warn('Roles missing; running RolePermissionSeeder first.');
            $this->call(RolePermissionSeeder::class);
        }

        $roleProviderId = Role::query()->where('name', 'provider')->value('id');
        $roleCustomerId = Role::query()->where('name', 'customer')->value('id');
        $roleAdminId = Role::query()->where('name', 'admin')->value('id')
            ?? Role::query()->where('name', 'super_admin')->value('id');

        if (! $roleProviderId || ! $roleCustomerId || ! $roleAdminId) {
            $this->command->error('Could not resolve role IDs. Run RolePermissionSeeder.');

            return;
        }

        // --- 1. Service categories ---
        $this->command->info('Creating service categories...');

        $categories = [
            ['name' => 'Plumbing', 'description' => 'Pipe repairs, leaks, installations'],
            ['name' => 'Electrical', 'description' => 'Wiring, repairs, installations'],
            ['name' => 'Cleaning', 'description' => 'Home and office cleaning'],
            ['name' => 'Painting', 'description' => 'Interior and exterior painting'],
            ['name' => 'AC Repair', 'description' => 'Cooling and heating systems'],
            ['name' => 'Carpentry', 'description' => 'Furniture, cabinets, doors'],
            ['name' => 'Gardening', 'description' => 'Lawn care, landscaping'],
            ['name' => 'Moving', 'description' => 'Home and office moving'],
        ];

        foreach ($categories as $cat) {
            ServiceCategory::query()->updateOrCreate(
                ['name' => $cat['name']],
                ['description' => $cat['description'], 'icon_url' => null]
            );
        }

        // --- 2. Services (base_price matches catalog; no duration column in schema) ---
        $this->command->info('Creating demo services...');

        $servicesList = [
            ['name' => 'Pipe Repair', 'category' => 'Plumbing', 'base_price' => 30],
            ['name' => 'Toilet Installation', 'category' => 'Plumbing', 'base_price' => 50],
            ['name' => 'Drain Cleaning', 'category' => 'Plumbing', 'base_price' => 40],
            ['name' => 'Water Heater Repair', 'category' => 'Plumbing', 'base_price' => 45],
            ['name' => 'Light Installation', 'category' => 'Electrical', 'base_price' => 25],
            ['name' => 'Outlet Repair', 'category' => 'Electrical', 'base_price' => 20],
            ['name' => 'Circuit Breaker', 'category' => 'Electrical', 'base_price' => 60],
            ['name' => 'Wiring Upgrade', 'category' => 'Electrical', 'base_price' => 100],
            ['name' => 'Home Cleaning', 'category' => 'Cleaning', 'base_price' => 50],
            ['name' => 'Office Cleaning', 'category' => 'Cleaning', 'base_price' => 80],
            ['name' => 'Deep Cleaning', 'category' => 'Cleaning', 'base_price' => 120],
            ['name' => 'Window Cleaning', 'category' => 'Cleaning', 'base_price' => 40],
            ['name' => 'Room Painting', 'category' => 'Painting', 'base_price' => 150],
            ['name' => 'Wall Repair', 'category' => 'Painting', 'base_price' => 60],
            ['name' => 'Full House Painting', 'category' => 'Painting', 'base_price' => 500],
            ['name' => 'AC Maintenance', 'category' => 'AC Repair', 'base_price' => 40],
            ['name' => 'AC Repair', 'category' => 'AC Repair', 'base_price' => 60],
            ['name' => 'AC Installation', 'category' => 'AC Repair', 'base_price' => 100],
            ['name' => 'Furniture Assembly', 'category' => 'Carpentry', 'base_price' => 40],
            ['name' => 'Cabinet Repair', 'category' => 'Carpentry', 'base_price' => 50],
            ['name' => 'Door Installation', 'category' => 'Carpentry', 'base_price' => 60],
            ['name' => 'Lawn Mowing', 'category' => 'Gardening', 'base_price' => 30],
            ['name' => 'Tree Trimming', 'category' => 'Gardening', 'base_price' => 50],
            ['name' => 'Garden Design', 'category' => 'Gardening', 'base_price' => 150],
            ['name' => 'Small Move', 'category' => 'Moving', 'base_price' => 100],
            ['name' => 'Large Move', 'category' => 'Moving', 'base_price' => 250],
            ['name' => 'Packing Service', 'category' => 'Moving', 'base_price' => 80],
        ];

        foreach ($servicesList as $svc) {
            $category = ServiceCategory::query()->where('name', $svc['category'])->first();
            if ($category) {
                Service::query()->updateOrCreate(
                    ['name' => $svc['name']],
                    [
                        'category_id' => $category->id,
                        'description' => $svc['name'].' service',
                        'base_price' => $svc['base_price'],
                        'icon_url' => null,
                    ]
                );
            }
        }

        // --- 3. Providers (profile on User; Provider links services) ---
        $this->command->info('Creating demo providers...');

        $locations = ['Beirut', 'Hamra', 'Ashrafieh', 'Verdun', 'Jounieh', 'Byblos', 'Tripoli', 'Zahle'];
        $phoneNumbers = ['71 123 456', '70 234 567', '76 345 678', '81 456 789', '03 567 890', '79 678 901'];

        $baseLat = 33.8938;
        $baseLng = 35.5018;

        $providersData = [
            ['name' => 'Beirut Plumbing Experts', 'service' => 'Pipe Repair', 'rating' => 4.9, 'experience' => '15+ years', 'urgent' => true],
            ['name' => 'Quick Flow Plumbing', 'service' => 'Drain Cleaning', 'rating' => 4.7, 'experience' => '10 years', 'urgent' => true],
            ['name' => 'Elite Plumbing Services', 'service' => 'Water Heater Repair', 'rating' => 4.8, 'experience' => '12 years', 'urgent' => false],
            ['name' => 'Beirut Electric Pro', 'service' => 'Light Installation', 'rating' => 4.8, 'experience' => '12 years', 'urgent' => true],
            ['name' => 'Safe Wire Electrical', 'service' => 'Outlet Repair', 'rating' => 4.6, 'experience' => '8 years', 'urgent' => false],
            ['name' => 'Power Solutions', 'service' => 'Circuit Breaker', 'rating' => 4.9, 'experience' => '15 years', 'urgent' => true],
            ['name' => 'Sparkle Clean', 'service' => 'Home Cleaning', 'rating' => 4.8, 'experience' => '8 years', 'urgent' => false],
            ['name' => 'Fresh Start Cleaning', 'service' => 'Deep Cleaning', 'rating' => 4.7, 'experience' => '6 years', 'urgent' => false],
            ['name' => 'EcoClean Services', 'service' => 'Office Cleaning', 'rating' => 4.6, 'experience' => '5 years', 'urgent' => false],
            ['name' => 'Perfect Paint', 'service' => 'Room Painting', 'rating' => 4.8, 'experience' => '10 years', 'urgent' => false],
            ['name' => 'Color Masters', 'service' => 'Full House Painting', 'rating' => 4.7, 'experience' => '12 years', 'urgent' => false],
            ['name' => 'Cool Air AC', 'service' => 'AC Repair', 'rating' => 4.9, 'experience' => '12 years', 'urgent' => true],
            ['name' => 'Arctic Cooling', 'service' => 'AC Maintenance', 'rating' => 4.7, 'experience' => '8 years', 'urgent' => false],
            ['name' => 'Wood Masters', 'service' => 'Furniture Assembly', 'rating' => 4.8, 'experience' => '15 years', 'urgent' => false],
            ['name' => 'Precision Carpentry', 'service' => 'Cabinet Repair', 'rating' => 4.6, 'experience' => '10 years', 'urgent' => false],
            ['name' => 'Green Thumb', 'service' => 'Lawn Mowing', 'rating' => 4.7, 'experience' => '7 years', 'urgent' => false],
            ['name' => 'Garden Paradise', 'service' => 'Garden Design', 'rating' => 4.9, 'experience' => '12 years', 'urgent' => false],
            ['name' => 'Safe Move', 'service' => 'Small Move', 'rating' => 4.8, 'experience' => '10 years', 'urgent' => false],
            ['name' => 'Express Movers', 'service' => 'Large Move', 'rating' => 4.7, 'experience' => '8 years', 'urgent' => true],
        ];

        $reviewsText = [
            'positive' => [
                'Excellent service! Very professional.',
                'Fast response, fixed everything quickly.',
                'Good quality work, reasonable price.',
                'Very helpful and friendly staff.',
                'Arrived on time, did a great job!',
                'Highly recommended! Will use again.',
                'Professional and clean work.',
                'Great experience from start to finish.',
                'Best service I have ever used!',
                'Very knowledgeable and efficient.',
            ],
            'neutral' => [
                'Good service, would recommend.',
                'Decent work, fair price.',
                'Got the job done.',
                'Satisfied with the service.',
            ],
        ];

        $createdProviders = [];

        foreach ($providersData as $index => $data) {
            $email = Str::slug($data['name'], '.').'@demo.nexvex.test';
            $loc = $locations[$index % count($locations)];

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'role_id' => $roleProviderId,
                    'email_verified_at' => now(),
                    'phone' => $phoneNumbers[$index % count($phoneNumbers)],
                    'address' => $loc.', Lebanon',
                ]
            );

            $offset = ($index * 0.01);
            $provider = Provider::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'bio' => 'Professional '.$data['service'].' with '.$data['experience'].' experience. '
                        .'Serving '.$loc.' and surrounding areas. Quality work guaranteed.',
                    'experience_years' => $this->parseExperienceYears($data['experience']),
                    'rating_avg' => $data['rating'],
                    'total_reviews' => 0,
                    'is_active' => true,
                    'is_verified' => true,
                    'verified_at' => now(),
                    'allow_chat' => true,
                    'latitude' => $baseLat + $offset,
                    'longitude' => $baseLng + $offset,
                ]
            );

            $service = Service::query()->where('name', $data['service'])->first();
            if ($service) {
                ProviderService::query()->updateOrCreate(
                    [
                        'provider_id' => $provider->id,
                        'service_id' => $service->id,
                    ],
                    [
                        'price' => (float) $service->base_price,
                    ]
                );
            }

            $createdProviders[] = $provider;
        }

        // --- 4. Customers ---
        $this->command->info('Creating demo customers...');

        $customers = [
            ['name' => 'Ali Hassan', 'email' => 'ali@demo.nexvex.test', 'phone' => '71 111 222'],
            ['name' => 'Sara Khalil', 'email' => 'sara@demo.nexvex.test', 'phone' => '70 222 333'],
            ['name' => 'Mohammad Taha', 'email' => 'mohammad@demo.nexvex.test', 'phone' => '76 333 444'],
            ['name' => 'Lina Maalouf', 'email' => 'lina@demo.nexvex.test', 'phone' => '81 444 555'],
            ['name' => 'Karim Nader', 'email' => 'karim@demo.nexvex.test', 'phone' => '03 555 666'],
            ['name' => 'Nadia Rayes', 'email' => 'nadia@demo.nexvex.test', 'phone' => '79 666 777'],
            ['name' => 'Omar Farhat', 'email' => 'omar@demo.nexvex.test', 'phone' => '71 777 888'],
            ['name' => 'Rana Sayegh', 'email' => 'rana@demo.nexvex.test', 'phone' => '70 888 999'],
            ['name' => 'Hadi Bou Khalil', 'email' => 'hadi@demo.nexvex.test', 'phone' => '76 999 000'],
            ['name' => 'Maya Gemayel', 'email' => 'maya@demo.nexvex.test', 'phone' => '81 000 111'],
        ];

        $customerUsers = [];
        foreach ($customers as $cust) {
            $customerUsers[] = User::query()->updateOrCreate(
                ['email' => $cust['email']],
                [
                    'name' => $cust['name'],
                    'password' => 'password',
                    'role_id' => $roleCustomerId,
                    'email_verified_at' => now(),
                    'phone' => $cust['phone'],
                    'address' => 'Beirut, Lebanon',
                ]
            );
        }

        // --- 5. Reviews (each review requires a completed booking) ---
        $this->command->info('Creating demo reviews...');

        foreach ($createdProviders as $provider) {
            $numReviews = random_int(10, 30);
            $primaryService = $provider->services()->first();

            for ($i = 0; $i < $numReviews; $i++) {
                $customer = $customerUsers[array_rand($customerUsers)];
                $rating = random_int(3, 5);
                $commentType = $rating >= 4 ? 'positive' : 'neutral';
                $comment = $reviewsText[$commentType][array_rand($reviewsText[$commentType])];

                $scheduled = Carbon::now()->subDays(random_int(1, 180));

                $price = $primaryService && $primaryService->pivot
                    ? (float) $primaryService->pivot->price
                    : (float) ($primaryService->base_price ?? 50);

                $booking = Booking::query()->create([
                    'customer_id' => $customer->id,
                    'provider_id' => $provider->id,
                    'service_id' => $primaryService?->id,
                    'price' => $price,
                    'scheduled_at' => $scheduled,
                    'duration_minutes' => 60,
                    'status' => 'completed',
                    'accepted_at' => $scheduled->copy()->subHour(),
                    'completed_at' => $scheduled->copy()->addHours(2),
                ]);

                Review::query()->create([
                    'booking_id' => $booking->id,
                    'customer_id' => $customer->id,
                    'provider_id' => $provider->id,
                    'rating' => $rating,
                    'comment' => $comment,
                    'created_at' => $scheduled->copy()->addDay(),
                ]);
            }

            $provider->total_reviews = Review::query()->where('provider_id', $provider->id)->count();
            $avg = Review::query()->where('provider_id', $provider->id)->avg('rating');
            $provider->rating_avg = round((float) $avg, 2);
            $provider->save();
        }

        // --- 6. Extra mixed-status bookings (no duplicate reviews on same booking) ---
        $this->command->info('Creating additional demo bookings...');

        $statuses = ['pending', 'accepted', 'rejected', 'reschedule_requested', 'completed', 'cancelled'];

        for ($i = 0; $i < 100; $i++) {
            $provider = $createdProviders[array_rand($createdProviders)];
            $customer = $customerUsers[array_rand($customerUsers)];
            $service = $provider->services()->first();
            $status = $statuses[array_rand($statuses)];
            $scheduled = Carbon::now()->subDays(random_int(1, 90))->addHours(random_int(0, 48));

            $price = $service && $service->pivot
                ? (float) $service->pivot->price
                : (float) ($service->base_price ?? random_int(30, 150));

            $row = [
                'customer_id' => $customer->id,
                'provider_id' => $provider->id,
                'service_id' => $service?->id,
                'price' => $price,
                'scheduled_at' => $scheduled,
                'duration_minutes' => 60,
                'status' => $status,
            ];

            if ($status === 'completed') {
                $row['accepted_at'] = $scheduled->copy()->subHour();
                $row['completed_at'] = $scheduled->copy()->addHours(2);
            } elseif ($status === 'accepted') {
                $row['accepted_at'] = $scheduled->copy()->subHour();
            } elseif ($status === 'cancelled') {
                $row['cancelled_at'] = $scheduled->copy()->subHour();
            }

            Booking::query()->create($row);
        }

        // --- 7. Admin ---
        $this->command->info('Creating demo admin account...');

        User::query()->updateOrCreate(
            ['email' => 'admin@nexvex.com'],
            [
                'name' => 'Admin NexVex',
                'password' => 'password',
                'role_id' => $roleAdminId,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Demo data seeded successfully.');
        $this->command->info('Total providers: '.Provider::query()->count());
        $this->command->info('Total reviews: '.Review::query()->count());
        $this->command->info('Total bookings: '.Booking::query()->count());
        $this->command->info('Total customers: '.User::query()->where('role_id', $roleCustomerId)->count());
        $this->command->newLine();
        $this->command->info('Demo accounts (password: password):');
        $this->command->info('  Admin: admin@nexvex.com');
        $this->command->info('  Customer: ali@demo.nexvex.test');
        $this->command->info('  Provider: '.Str::slug($providersData[0]['name'], '.').'@demo.nexvex.test');
    }

    protected function parseExperienceYears(string $exp): int
    {
        if (preg_match('/(\d+)/', $exp, $m)) {
            return min(50, max(0, (int) $m[1]));
        }

        return 5;
    }
}
