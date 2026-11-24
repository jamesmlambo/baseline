<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quotations for customers without a booking (PAY-01): their contact details (encrypted, as
 * on bookings), a note shown on the quote, and a private link for email and WhatsApp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->text('recipient_email')->nullable()->after('recipient_name');
            $table->text('recipient_phone')->nullable()->after('recipient_email');
            $table->text('notes')->nullable()->after('valid_until');
            $table->string('public_token', 48)->nullable()->unique()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropUnique(['public_token']);
            $table->dropColumn(['recipient_email', 'recipient_phone', 'notes', 'public_token']);
        });
    }
};
