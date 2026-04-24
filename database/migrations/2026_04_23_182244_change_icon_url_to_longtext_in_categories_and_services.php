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
        Schema::table('service_categories', function (Blueprint $table) {
            $table->longText('icon_url')->nullable()->change();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->longText('icon_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->string('icon_url', 2048)->nullable()->change();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('icon_url', 2048)->nullable()->change();
        });
    }
};
