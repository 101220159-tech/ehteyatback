<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FINAL SCHEMA — SP Capstone Project
 *
 * Complete rebuild with:
 * - UUID primary keys on all domain tables
 * - Zones → Areas (polygon coordinate arrays from Google Maps)
 * - Provider: is_vip, is_active, is_busy, allow_chat, zone assignment, subscription
 * - Provider certifications + official documents
 * - Full booking lifecycle (pending → accepted/rejected → reschedule → completed)
 * - Payment / subscription tracking
 * - AI chat tables (schema only, logic TBD)
 * - Google OAuth field on users
 *
 * Run: php artisan migrate:fresh  (drops everything and reruns all migrations)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // ── Drop every legacy table ──────────────────────────────────────────
        $legacy = [
            'ai_messages', 'ai_conversations',
            'chatbot_messages', 'chatbot_conversations',
            'booking_reschedule_requests',
            'notifications', 'messages', 'chats',
            'reviews', 'bookings',
            'project_images', 'projects',
            'payments',
            'provider_zones', 'provider_availability', 'provider_services',
            'provider_documents', 'provider_certifications',
            'providers',
            'areas', 'zones',
            'services', 'service_categories',
            'user_permissions', 'role_permissions',
            'permissions', 'roles',
            'user_addresses', 'cities',
            'sessions', 'personal_access_tokens',
            'users',
            'portfolio_samples', 'chats_messages', 'conversations',
            'provider_zones', 'zone_locations',
            'communities', 'memos', 'user_profile_memories', 'user_roles',
        ];
        foreach ($legacy as $t) {
            Schema::dropIfExists($t);
        }

        Schema::enableForeignKeyConstraints();

        // ════════════════════════════════════════════════════════════════════
        // ROLES & PERMISSIONS
        // ════════════════════════════════════════════════════════════════════

        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();           // super_admin | admin | provider | customer
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUuid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        // ════════════════════════════════════════════════════════════════════
        // USERS
        // ════════════════════════════════════════════════════════════════════

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();           // nullable — Google-only users have no password
            $table->string('google_id')->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->foreignUuid('role_id')->constrained('roles')->restrictOnDelete();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('address')->nullable();
            $table->string('fcm_token', 512)->nullable();
            $table->string('avatar_url', 2048)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignUuid('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->primary(['user_id', 'permission_id']);
        });

        // ════════════════════════════════════════════════════════════════════
        // SERVICE CATALOG
        // ════════════════════════════════════════════════════════════════════

        Schema::create('service_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon_url', 2048)->nullable();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('service_categories')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon_url', 2048)->nullable();
            $table->decimal('base_price', 10, 2)->nullable();
            $table->timestamps();
        });

        // ════════════════════════════════════════════════════════════════════
        // GEOGRAPHIC ZONES & AREAS
        // Each Zone groups multiple Areas.
        // Each Area is a named neighbourhood/region with a polygon (array of
        // {lat, lng} points) captured via Google Maps on the frontend.
        // ════════════════════════════════════════════════════════════════════

        Schema::create('zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->string('name');                          // e.g. "Hamra", "Rawshe"
            // Polygon: JSON array of [{lat: x, lng: y}, …] sent by the frontend
            $table->json('coordinates');
            $table->timestamps();
        });

        // ════════════════════════════════════════════════════════════════════
        // PROVIDERS
        // ════════════════════════════════════════════════════════════════════

        Schema::create('providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete()->unique();
            $table->text('bio')->nullable();
            $table->unsignedSmallInteger('experience_years')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0.00);
            $table->unsignedInteger('total_reviews')->default(0);

            // Status
            $table->boolean('is_active')->default(false);   // provider toggles this
            $table->boolean('is_busy')->default(false);     // set automatically when booking starts
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();

            // VIP & subscription (admin-managed)
            $table->boolean('is_vip')->default(false);
            $table->timestamp('subscription_expires_at')->nullable();

            // Chat toggle (admin-managed, provider cannot change)
            $table->boolean('allow_chat')->default(false);

            $table->timestamps();
        });

        // Provider ↔ Zone (many-to-many, admin-managed)
        Schema::create('provider_zones', function (Blueprint $table) {
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignUuid('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->primary(['provider_id', 'zone_id']);
            $table->timestamps();
        });

        // Provider certifications (multiple per provider)
        Schema::create('provider_certifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('title');
            $table->string('issuer')->nullable();
            $table->string('file_url', 2048)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();
        });

        // Official documents (id_card, trade_license, insurance, etc.)
        Schema::create('provider_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->enum('type', ['id_card', 'trade_license', 'insurance', 'certificate', 'other']);
            $table->string('title');
            $table->string('file_url', 2048);
            $table->timestamps();
        });

        // Services offered by provider (with custom price)
        Schema::create('provider_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->timestamps();
            $table->unique(['provider_id', 'service_id']);
        });

        // Weekly availability schedule
        Schema::create('provider_availability', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            // 0 = Sunday … 6 = Saturday
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->unique(['provider_id', 'day_of_week', 'start_time', 'end_time'], 'pa_provider_day_time_unique');
        });

        // Portfolio projects
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('project_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('image_url', 2048);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
        });

        // ════════════════════════════════════════════════════════════════════
        // PAYMENTS / SUBSCRIPTIONS  (admin records payments from providers)
        // ════════════════════════════════════════════════════════════════════

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('type', ['subscription', 'vip_upgrade']);
            $table->text('description')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();    // subscription end date
            $table->enum('status', ['pending', 'completed', 'expired'])->default('pending');
            $table->timestamps();
        });

        // ════════════════════════════════════════════════════════════════════
        // BOOKINGS
        // ════════════════════════════════════════════════════════════════════

        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignUuid('service_id')->nullable()->constrained('services')->nullOnDelete();

            $table->dateTime('scheduled_at');               // requested start time
            $table->unsignedSmallInteger('duration_minutes')->default(60);

            $table->enum('status', [
                'pending',              // awaiting provider response
                'accepted',             // provider accepted
                'rejected',             // provider rejected
                'reschedule_requested', // reschedule proposed (by either party)
                'completed',            // work done
                'cancelled',            // customer cancelled
            ])->default('pending');

            $table->text('customer_notes')->nullable();
            $table->decimal('customer_latitude', 10, 8)->nullable();
            $table->decimal('customer_longitude', 11, 8)->nullable();
            $table->string('customer_address')->nullable();

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();

            $table->timestamps();
            $table->index(['provider_id', 'scheduled_at']);
            $table->index(['customer_id', 'status']);
        });

        // Reschedule requests (either party can propose)
        Schema::create('booking_reschedule_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignUuid('requested_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('proposed_at');                // new proposed time
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });

        // ════════════════════════════════════════════════════════════════════
        // REVIEWS
        // ════════════════════════════════════════════════════════════════════

        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained('bookings')->cascadeOnDelete()->unique();
            $table->foreignUuid('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');          // 1–5
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        // ════════════════════════════════════════════════════════════════════
        // CHAT (customer ↔ provider, only when provider.allow_chat = true)
        // ════════════════════════════════════════════════════════════════════

        Schema::create('chats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'provider_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->enum('type', ['text', 'image'])->default('text');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['chat_id', 'created_at']);
        });

        // ════════════════════════════════════════════════════════════════════
        // NOTIFICATIONS
        // ════════════════════════════════════════════════════════════════════

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('type')->nullable();             // booking_update | payment | system …
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        // ════════════════════════════════════════════════════════════════════
        // AI CHAT  (schema only — logic implemented by team member)
        // ════════════════════════════════════════════════════════════════════

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_id')->nullable();       // for guest users
            $table->timestamps();
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->text('content');
            $table->timestamps();
        });

        // ════════════════════════════════════════════════════════════════════
        // LARAVEL INTERNALS
        // ════════════════════════════════════════════════════════════════════

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('tokenable');               // UUID-safe morph
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        // Intentionally left as a no-op.
        // Use `php artisan migrate:fresh` to reset.
    }
};
