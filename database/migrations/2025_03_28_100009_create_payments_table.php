<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Online payments were removed — customers and providers settle in person.
 * The `payments` table is not created on new installs.
 * Run `2025_03_29_000000_drop_payments_table` to remove a legacy `payments` table.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
