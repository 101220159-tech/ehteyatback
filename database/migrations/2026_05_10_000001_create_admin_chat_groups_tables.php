<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_chat_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 160);
            $table->timestamps();
        });

        Schema::create('admin_chat_group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('admin_chat_group_id')->constrained('admin_chat_groups')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['admin_chat_group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_chat_group_user');
        Schema::dropIfExists('admin_chat_groups');
    }
};
