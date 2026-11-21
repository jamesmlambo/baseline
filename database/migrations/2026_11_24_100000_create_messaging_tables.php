<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Messaging (FINAL_SPECIFICATION §14) and WhatsApp price lists (§10).
 *
 * - message_templates: one row per (key, channel, language); editing the body bumps the version.
 * - messages: every SMS/WhatsApp ever attempted, with status, retries, cost and an idempotency
 *   key so the same automation never sends twice (MSG-02, MSG-05).
 * - price_lists: secure, expiring links reception sends on WhatsApp (WA-01).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64);
            $table->string('channel', 16);
            $table->string('language', 8)->default('en');
            $table->unsignedInteger('version')->default(1);
            $table->string('name');
            $table->text('body');
            $table->string('classification', 16);
            $table->boolean('is_critical')->default(false);
            $table->boolean('is_active')->default(true);
            $table->bigInteger('cost_estimate_cents')->default(0);
            $table->string('provider_template_id')->nullable();
            $table->timestampsTz();

            $table->unique(['key', 'channel', 'language']);
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_template_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('template_version')->nullable();
            $table->string('channel', 16);
            $table->string('classification', 16);
            $table->text('recipient');
            $table->string('recipient_hash', 64)->index();
            $table->text('body');
            $table->string('status', 16);
            $table->string('skip_reason')->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32)->nullable();
            $table->string('provider_message_id')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->bigInteger('cost_cents')->default(0);
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampsTz();

            $table->index(['channel', 'status', 'created_at']);
        });

        Schema::table('guests', function (Blueprint $table): void {
            $table->jsonb('channel_opt_outs')->nullable()->after('marketing_consent_at');
        });

        Schema::create('price_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('token', 64)->unique();
            $table->date('arrival_date');
            $table->date('departure_date');
            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children')->default(0);
            $table->string('customer_name')->nullable();
            $table->text('customer_phone')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('first_opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('followed_up_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE price_lists ADD CONSTRAINT price_lists_dates_valid CHECK (departure_date > arrival_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('price_lists');
        Schema::table('guests', fn (Blueprint $table) => $table->dropColumn('channel_opt_outs'));
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_templates');
    }
};
