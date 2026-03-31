<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->string('owner_type');
            $table->string('type');
            $table->text('body');
            $table->text('embedding_vector')->nullable();
            $table->timestamps();
            $table->index(['owner_type', 'owner_id']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memos');
    }
};
