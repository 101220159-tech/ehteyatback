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
        // users.avatar_url
        Schema::table('users', function (Blueprint $table) {
            $table->longText('avatar_url')->nullable()->change();
        });

        // provider_certifications.file_url
        Schema::table('provider_certifications', function (Blueprint $table) {
            $table->longText('file_url')->nullable()->change();
        });

        // provider_documents.file_url
        Schema::table('provider_documents', function (Blueprint $table) {
            $table->longText('file_url')->nullable()->change();
        });

        // project_images.image_url
        Schema::table('project_images', function (Blueprint $table) {
            $table->longText('image_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_url', 2048)->nullable()->change();
        });
        Schema::table('provider_certifications', function (Blueprint $table) {
            $table->string('file_url', 2048)->nullable()->change();
        });
        Schema::table('provider_documents', function (Blueprint $table) {
            $table->string('file_url', 2048)->nullable()->change();
        });
        Schema::table('project_images', function (Blueprint $table) {
            $table->string('image_url', 2048)->nullable()->change();
        });
    }
};
