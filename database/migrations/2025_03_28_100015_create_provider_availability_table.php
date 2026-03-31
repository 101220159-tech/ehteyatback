<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->date('date_override')->nullable();
            $table->timestamps();
            $table->index(['provider_id', 'day_of_week']);
            $table->index(['provider_id', 'date_override']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_availability');
    }
};
