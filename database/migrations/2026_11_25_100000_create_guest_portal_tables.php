<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guest portal (FINAL_SPECIFICATION §13).
 *
 * - rooms.portal_token: the opaque, rotatable code in each room's QR (GST-01).
 * - portal_codes: one-time codes, stored only as hashes (GST-02/03).
 * - booking_authorized_phones: extra numbers the booker allowed (GST-04).
 * - guest_requests / guest_messages: requests and the reception inbox (GST-07/08).
 * - menu_items, reviews: meal ordering and moderated reviews.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->string('portal_token', 40)->nullable()->unique()->after('code');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->timestampTz('portal_revoked_at')->nullable()->after('checked_out_by');
        });

        Schema::create('portal_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('phone_hash', 64);
            $table->string('code_hash');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestampsTz();

            $table->index(['booking_id', 'created_at']);
        });

        Schema::create('booking_authorized_phones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80)->nullable();
            $table->text('phone');
            $table->string('phone_hash', 64);
            $table->string('added_by', 24);
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['booking_id', 'phone_hash']);
        });

        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('allergens')->nullable();
            $table->bigInteger('price_cents');
            $table->boolean('is_available')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE menu_items ADD CONSTRAINT menu_items_price_non_negative CHECK (price_cents >= 0)');

        Schema::create('guest_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 24);
            $table->string('status', 24);
            $table->text('details')->nullable();
            $table->jsonb('items')->nullable();
            $table->bigInteger('total_cents')->default(0);
            $table->boolean('charge_to_room')->default(false);
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedSmallInteger('escalation_level')->default(0);
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
        });

        Schema::create('guest_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 8);
            $table->text('body');
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('read_at')->nullable();
            $table->unsignedSmallInteger('escalation_level')->default(0);
            $table->timestampsTz();

            $table->index(['booking_id', 'created_at']);
        });

        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('rating');
            $table->text('comment')->nullable();
            $table->string('display_name', 80)->nullable();
            $table->boolean('consent_to_publish')->default(false);
            $table->string('status', 16);
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('moderated_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_range CHECK (rating BETWEEN 1 AND 5)');
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_publish_needs_consent CHECK (status <> 'approved' OR consent_to_publish)");
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('guest_messages');
        Schema::dropIfExists('guest_requests');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('booking_authorized_phones');
        Schema::dropIfExists('portal_codes');
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('portal_revoked_at'));
        Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn('portal_token'));
    }
};
