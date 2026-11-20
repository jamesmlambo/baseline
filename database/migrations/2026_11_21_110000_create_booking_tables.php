<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Booking engine (BOOK-*, INV-02..07).
 *
 * Inventory is a room-night ledger: one active claim per (room, night),
 * enforced by a partial unique index. Released claims (cancelled, expired,
 * moved, unblocked) keep released_at and stay as history without blocking
 * new claims (INV-03). Booking-room assignments carry a second safeguard:
 * a GiST exclusion constraint on overlapping active stays (INV-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->string('status', 24);
            $table->string('payment_status', 32)->default('unpaid');
            $table->string('source', 24);
            $table->string('external_reference')->nullable();
            $table->date('arrival_date');
            $table->date('departure_date');
            $table->string('lead_guest_name');
            $table->string('lead_guest_email')->nullable();
            $table->string('lead_guest_phone', 32)->nullable();
            $table->timestampTz('hold_expires_at')->nullable();
            $table->bigInteger('total_cents')->default(0);
            $table->text('special_requests')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['status', 'hold_expires_at']);
            $table->index(['arrival_date', 'departure_date']);
        });

        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_dates_valid CHECK (departure_date > arrival_date)');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_hold_has_expiry CHECK (status <> 'hold' OR hold_expires_at IS NOT NULL)");
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_total_non_negative CHECK (total_cents >= 0)');

        Schema::create('booking_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accommodation_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->date('arrival_date');
            $table->date('departure_date');
            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children')->default(0);
            $table->bigInteger('accommodation_total_cents');
            $table->timestampTz('released_at')->nullable();
            $table->string('release_reason', 32)->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE booking_rooms ADD CONSTRAINT booking_rooms_dates_valid CHECK (departure_date > arrival_date)');
        DB::statement('ALTER TABLE booking_rooms ADD CONSTRAINT booking_rooms_guests_valid CHECK (adults >= 1)');
        DB::statement("ALTER TABLE booking_rooms ADD CONSTRAINT booking_rooms_no_overlap EXCLUDE USING gist (room_id WITH =, daterange(arrival_date, departure_date, '[)') WITH &&) WHERE (released_at IS NULL)");

        Schema::create('room_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason', 24);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        // ends_on is exclusive, like a departure date: a block 10–12 covers the nights of the 10th and 11th.
        DB::statement('ALTER TABLE room_blocks ADD CONSTRAINT room_blocks_dates_valid CHECK (ends_on > starts_on)');

        Schema::create('room_nights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->date('night_date');
            $table->foreignId('booking_room_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('room_block_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestampTz('released_at')->nullable();
            $table->timestampsTz();

            $table->index(['night_date', 'room_id']);
        });

        DB::statement('ALTER TABLE room_nights ADD CONSTRAINT room_nights_single_owner CHECK (num_nonnulls(booking_room_id, room_block_id) = 1)');
        DB::statement('CREATE UNIQUE INDEX room_nights_one_active_claim ON room_nights (room_id, night_date) WHERE released_at IS NULL');

        Schema::create('booking_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_status_histories');
        Schema::dropIfExists('room_nights');
        Schema::dropIfExists('room_blocks');
        Schema::dropIfExists('booking_rooms');
        Schema::dropIfExists('bookings');
    }
};
