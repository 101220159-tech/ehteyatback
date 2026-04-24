<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_availability', function (Blueprint $table) {
            $table->string('day_of_week', 16)->change();
        });
    }

    public function down(): void
    {
        Schema::table('provider_availability', function (Blueprint $table) {
            $table->unsignedTinyInteger('day_of_week')->change();
        });
    }
};
