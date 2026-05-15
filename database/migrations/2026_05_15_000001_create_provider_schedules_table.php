<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignUuid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->date('scheduled_date');
            $table->time('scheduled_time');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->enum('status', ['pending', 'accepted', 'completed', 'cancelled'])->default('accepted');
            $table->timestamps();

            $table->unique('booking_id');
            $table->index(['provider_id', 'scheduled_date']);
            $table->index(['provider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_schedules');
    }
};
