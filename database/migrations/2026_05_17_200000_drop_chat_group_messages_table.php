<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('chat_group_messages');
    }

    public function down(): void
    {
        // Legacy table — not recreated; use admin_chat_group_messages for group chat.
    }
};
