<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            if (! Schema::hasColumn('providers', 'status')) {
                $table->string('status')->default('pending');
            }

            if (! Schema::hasColumn('providers', 'is_verified')) {
                $table->boolean('is_verified')->default(false);
            }

            if (! Schema::hasColumn('providers', 'verified_at')) {
                $table->timestamp('verified_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            if (Schema::hasColumn('providers', 'verified_at')) {
                $table->dropColumn('verified_at');
            }

            if (Schema::hasColumn('providers', 'is_verified')) {
                $table->dropColumn('is_verified');
            }

            if (Schema::hasColumn('providers', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};

