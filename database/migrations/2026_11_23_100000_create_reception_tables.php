<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reception (FINAL_SPECIFICATION §11): check-in/out stamps, the opaque code printed as a QR on
 * documents (REC-06/07), the guest register (REC-04) and the front-desk log (REC-05).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('reception_code', 40)->nullable()->unique()->after('public_token');
            $table->timestampTz('checked_in_at')->nullable()->after('terms_accepted_at');
            $table->foreignId('checked_in_by')->nullable()->after('checked_in_at')->constrained('users')->nullOnDelete();
            $table->timestampTz('checked_out_at')->nullable()->after('checked_in_by');
            $table->foreignId('checked_out_by')->nullable()->after('checked_out_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('guest_register_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('id_type', 24)->nullable();
            $table->text('id_number')->nullable();
            $table->string('nationality', 64)->nullable();
            $table->text('home_address')->nullable();
            $table->string('vehicle_registration', 24)->nullable();
            $table->unsignedSmallInteger('number_of_guests')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('front_desk_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 24);
            $table->string('item', 120)->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->text('details')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['type', 'created_at']);
        });

        DB::statement('ALTER TABLE front_desk_logs ADD CONSTRAINT front_desk_logs_quantity_positive CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('front_desk_logs');
        Schema::dropIfExists('guest_register_entries');

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('checked_in_by');
            $table->dropConstrainedForeignId('checked_out_by');
            $table->dropColumn(['reception_code', 'checked_in_at', 'checked_out_at']);
        });
    }
};
