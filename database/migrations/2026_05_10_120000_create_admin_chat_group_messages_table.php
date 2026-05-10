<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_chat_group_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_chat_group_id')->constrained('admin_chat_groups')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('type', 32)->default('text');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['admin_chat_group_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_chat_group_messages');
    }
};
