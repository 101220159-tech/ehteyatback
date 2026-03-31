<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('coverages')->nullable();
            $table->json('connections')->nullable();
            $table->integer('connections_count')->default(0);
            $table->decimal('connections_percentage', 5, 2)->default(0);
            $table->foreignId('category_id')->constrained('service_categories')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['category_id', 'is_active']);
            $table->index('provider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
