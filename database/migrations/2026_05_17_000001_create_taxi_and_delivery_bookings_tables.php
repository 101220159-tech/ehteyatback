<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxi_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('users')->cascadeOnDelete();
            $table->json('pickup_location');
            $table->json('destination_location');
            $table->unsignedTinyInteger('passenger_count')->default(1);
            $table->string('vehicle_type', 32)->nullable();
            $table->decimal('distance_km', 10, 3);
            $table->decimal('estimated_duration_minutes', 10, 2);
            $table->decimal('estimated_price', 12, 2);
            $table->json('route_data')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        Schema::create('delivery_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('users')->cascadeOnDelete();
            $table->json('pickup_location');
            $table->json('dropoff_location');
            $table->enum('vehicle_type', ['motorcycle', 'car', 'truck']);
            $table->decimal('package_weight', 10, 2);
            $table->unsignedInteger('package_quantity')->default(1);
            $table->string('package_type')->nullable();
            $table->boolean('fragile')->default(false);
            $table->decimal('distance_km', 10, 3);
            $table->decimal('estimated_duration_minutes', 10, 2);
            $table->decimal('shipping_price', 12, 2);
            $table->json('route_data')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_bookings');
        Schema::dropIfExists('taxi_bookings');
    }
};
