<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guest profiles (BOOK-06, SEC-07) and bookable extras (BOOK-02, OBK-01).
 *
 * Email and phone are encrypted; *_hash columns hold keyed HMACs of the normalised
 * values so returning guests can be matched without decrypting every row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->text('email')->nullable();
            $table->string('email_hash', 64)->nullable()->index();
            $table->text('phone')->nullable();
            $table->string('phone_hash', 64)->nullable()->index();
            $table->text('notes')->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->timestampTz('marketing_consent_at')->nullable();
            $table->timestampsTz();

            $table->index(['last_name', 'first_name']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('guest_id')->nullable()->after('external_reference')->constrained()->nullOnDelete();
            $table->bigInteger('deposit_due_cents')->default(0)->after('total_cents');
            $table->string('preferred_payment_method', 24)->nullable()->after('deposit_due_cents');
            $table->timestampTz('terms_accepted_at')->nullable()->after('special_requests');
            $table->string('public_token', 64)->nullable()->unique()->after('reference');
        });

        Schema::create('extras', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('pricing_unit', 24);
            $table->bigInteger('price_cents');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_bookable_online')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE extras ADD CONSTRAINT extras_price_non_negative CHECK (price_cents >= 0)');

        Schema::create('booking_extras', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extra_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price_cents');
            $table->bigInteger('total_cents');
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE booking_extras ADD CONSTRAINT booking_extras_quantity_positive CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_extras');
        Schema::dropIfExists('extras');

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('guest_id');
            $table->dropColumn(['deposit_due_cents', 'preferred_payment_method', 'terms_accepted_at', 'public_token']);
        });

        Schema::dropIfExists('guests');
    }
};
