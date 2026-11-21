<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leisure services and food (FINAL_SPECIFICATION §18).
 *
 * - services: the bookable catalogue with hours, slots, capacity and rules (LSR-01/02).
 * - service_resources + service_booking_resources: therapists and treatment rooms; a GiST
 *   exclusion constraint makes double-booking a resource impossible (LSR-03).
 * - service_bookings: bookings by reception or in-house guests, charged to the room or paid (LSR-05).
 * - menu_category_rules + kitchen_tickets: meal ordering hours and the kitchen queue (LSR-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category', 24);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('slot_interval_minutes')->default(60);
            $table->bigInteger('price_cents');
            $table->string('pricing', 16)->default('per_person');
            $table->string('capacity_unit', 16)->default('people');
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->unsignedSmallInteger('max_party_size')->default(10);
            $table->unsignedSmallInteger('min_notice_minutes')->default(0);
            $table->unsignedSmallInteger('cancellation_cutoff_hours')->default(24);
            $table->jsonb('opening_hours');
            $table->jsonb('resource_types')->nullable();
            $table->text('restrictions')->nullable();
            $table->boolean('charge_to_room_allowed')->default(true);
            $table->boolean('bookable_by_guests')->default(true);
            $table->boolean('requires_indemnity')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('service_resources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type', 24);
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestampsTz();
        });

        Schema::create('service_bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->unsignedSmallInteger('party_size');
            $table->string('customer_name');
            $table->text('customer_phone')->nullable();
            $table->text('customer_email')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('confirmed');
            $table->bigInteger('total_cents');
            $table->boolean('charge_to_room')->default(false);
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('indemnity_accepted_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 16);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['service_id', 'starts_at']);
            $table->index(['booking_id']);
        });

        Schema::create('service_booking_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_resource_id')->constrained()->restrictOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->timestampTz('released_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('menu_category_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 64)->unique();
            $table->time('orders_open_at');
            $table->time('orders_close_at');
            $table->string('note')->nullable();
            $table->timestampsTz();
        });

        Schema::create('kitchen_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guest_request_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('service_booking_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('for_label');
            $table->jsonb('items');
            $table->text('allergens')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('wanted_at')->nullable();
            $table->string('status', 16)->default('new');
            $table->timestampTz('preparing_at')->nullable();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampsTz();

            $table->index('status');
        });

        DB::statement('ALTER TABLE services ADD CONSTRAINT services_price_check CHECK (price_cents >= 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_check CHECK (duration_minutes > 0 AND slot_interval_minutes > 0)');
        DB::statement('ALTER TABLE service_bookings ADD CONSTRAINT service_bookings_period_check CHECK (ends_at > starts_at)');
        DB::statement('ALTER TABLE service_bookings ADD CONSTRAINT service_bookings_party_check CHECK (party_size >= 1)');
        DB::statement('ALTER TABLE service_bookings ADD CONSTRAINT service_bookings_total_check CHECK (total_cents >= 0)');
        DB::statement('ALTER TABLE service_bookings ADD CONSTRAINT service_bookings_room_charge_needs_stay CHECK (NOT charge_to_room OR booking_id IS NOT NULL)');
        DB::statement("ALTER TABLE service_booking_resources ADD CONSTRAINT service_booking_resources_no_overlap EXCLUDE USING gist (service_resource_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&) WHERE (released_at IS NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_tickets');
        Schema::dropIfExists('menu_category_rules');
        Schema::dropIfExists('service_booking_resources');
        Schema::dropIfExists('service_bookings');
        Schema::dropIfExists('service_resources');
        Schema::dropIfExists('services');
    }
};
