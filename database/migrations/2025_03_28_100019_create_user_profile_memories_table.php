<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profile_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('memory_type');
            $table->text('content');
            $table->string('vector_id')->nullable();
            $table->unsignedTinyInteger('importance')->default(5);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'memory_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profile_memories');
    }
};
