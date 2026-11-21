<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * External channels (FINAL_SPECIFICATION §19, CHN-01..04). No provider is connected (PEND-07/08).
 *
 * - channels: Booking.com, LekkeSlaap… with driver, health and stale-sync threshold.
 * - channel_room_types: which of our room types an OTA room code means.
 * - channel_reservations: every OTA reservation seen, unique per channel + reference (idempotent import).
 * - channel_exceptions: reservations we could not place safely, and iCal clashes (DEC-02).
 * - channel_sync_runs: a log of every sync attempt (CHN-04).
 * - ical_links + ical_events: the limited iCal fallback per physical room (CHN-02).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('driver', 24)->default('null');
            $table->boolean('is_enabled')->default(false);
            $table->unsignedSmallInteger('stale_after_minutes')->default(60);
            $table->timestampTz('last_successful_sync_at')->nullable();
            $table->timestampTz('last_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
        });

        Schema::create('channel_room_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accommodation_type_id')->constrained()->cascadeOnDelete();
            $table->string('external_code', 64);
            $table->timestampsTz();

            $table->unique(['channel_id', 'external_code']);
        });

        Schema::create('channel_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->constrained()->restrictOnDelete();
            $table->string('external_reference', 64);
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16);
            $table->bigInteger('channel_total_cents')->nullable();
            $table->jsonb('payload');
            $table->timestampTz('last_seen_at');
            $table->timestampsTz();

            $table->unique(['channel_id', 'external_reference']);
        });

        Schema::create('channel_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('channel_reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 24);
            $table->string('summary');
            $table->text('details')->nullable();
            $table->string('status', 16)->default('open');
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->index('status');
        });

        Schema::create('channel_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ical_link_id')->nullable();
            $table->string('operation', 32);
            $table->string('status', 16);
            $table->unsignedInteger('items')->default(0);
            $table->text('message')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->index(['channel_id', 'started_at']);
        });

        Schema::create('ical_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('export_token', 48)->unique();
            $table->boolean('export_enabled')->default(false);
            $table->text('import_url')->nullable();
            $table->timestampTz('last_imported_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->unique(['channel_id', 'room_id']);
        });

        Schema::create('ical_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ical_link_id')->constrained()->cascadeOnDelete();
            $table->string('uid');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('summary')->nullable();
            $table->foreignId('room_block_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('removed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['ical_link_id', 'uid']);
        });

        DB::statement("ALTER TABLE channel_reservations ADD CONSTRAINT channel_reservations_status_check CHECK (status IN ('booked', 'modified', 'cancelled', 'exception'))");
        DB::statement("ALTER TABLE channel_exceptions ADD CONSTRAINT channel_exceptions_status_check CHECK (status IN ('open', 'resolved'))");
        DB::statement("ALTER TABLE channel_sync_runs ADD CONSTRAINT channel_sync_runs_status_check CHECK (status IN ('ok', 'failed', 'not_connected'))");
        DB::statement('ALTER TABLE ical_events ADD CONSTRAINT ical_events_dates_check CHECK (ends_on > starts_on)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ical_events');
        Schema::dropIfExists('ical_links');
        Schema::dropIfExists('channel_sync_runs');
        Schema::dropIfExists('channel_exceptions');
        Schema::dropIfExists('channel_reservations');
        Schema::dropIfExists('channel_room_types');
        Schema::dropIfExists('channels');
    }
};
