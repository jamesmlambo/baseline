<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INV-01: accommodation types (what guests book), physical rooms (what gets
 * assigned), and seasonal rates. Money is integer cents.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('accommodation_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('base_occupancy')->default(2);
            $table->unsignedSmallInteger('max_occupancy')->default(2);
            $table->bigInteger('base_nightly_rate_cents');
            $table->bigInteger('extra_guest_nightly_cents')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE accommodation_types ADD CONSTRAINT accommodation_types_rates_non_negative CHECK (base_nightly_rate_cents >= 0 AND extra_guest_nightly_cents >= 0)');
        DB::statement('ALTER TABLE accommodation_types ADD CONSTRAINT accommodation_types_occupancy_valid CHECK (base_occupancy >= 1 AND max_occupancy >= base_occupancy)');

        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accommodation_type_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->text('internal_notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestampsTz();
        });

        Schema::create('seasonal_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accommodation_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->bigInteger('nightly_rate_cents');
            $table->unsignedSmallInteger('min_nights')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE seasonal_rates ADD CONSTRAINT seasonal_rates_dates_valid CHECK (ends_on >= starts_on)');
        DB::statement('ALTER TABLE seasonal_rates ADD CONSTRAINT seasonal_rates_rate_non_negative CHECK (nightly_rate_cents >= 0)');
        // A night can only be priced by one season per accommodation type. Ranges are inclusive.
        DB::statement("ALTER TABLE seasonal_rates ADD CONSTRAINT seasonal_rates_no_overlap EXCLUDE USING gist (accommodation_type_id WITH =, daterange(starts_on, ends_on, '[]') WITH &&)");
    }

    public function down(): void
    {
        Schema::dropIfExists('seasonal_rates');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('accommodation_types');
    }
};
