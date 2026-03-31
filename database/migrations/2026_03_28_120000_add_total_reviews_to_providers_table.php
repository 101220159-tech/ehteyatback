<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('providers') && ! Schema::hasColumn('providers', 'total_reviews')) {
            Schema::table('providers', function (Blueprint $table) {
                $table->unsignedInteger('total_reviews')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('providers', 'total_reviews')) {
            Schema::table('providers', function (Blueprint $table) {
                $table->dropColumn('total_reviews');
            });
        }
    }
};
