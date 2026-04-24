<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('provider_earnings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamp('earned_at');
            $table->timestamps();

            $table->unique('booking_id');
            $table->index(['provider_id', 'earned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_earnings');
    }
};
