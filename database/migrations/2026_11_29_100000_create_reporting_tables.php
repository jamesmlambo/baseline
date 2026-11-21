<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reporting (FINAL_SPECIFICATION §19).
 *
 * - daily_reports: the stored 17:00 snapshot per day, plus the optional day-close freeze (REP-03/04).
 * - report_deliveries: one row per email / push attempt, with retries (REP-03).
 * - bookings.attribution / enquiries.attribution: first-touch campaign source (utm_* or the
 *   referring site's host only — no full URLs or IPs) for marketing measures (REP-06).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table): void {
            $table->id();
            $table->date('report_date')->unique();
            $table->jsonb('data');
            $table->timestampTz('generated_at');
            $table->jsonb('frozen_totals')->nullable();
            $table->timestampTz('frozen_at')->nullable();
            $table->foreignId('frozen_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('report_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();

            $table->unique(['daily_report_id', 'user_id', 'channel']);
            $table->index(['status', 'attempts']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->jsonb('attribution')->nullable();
        });

        Schema::table('enquiries', function (Blueprint $table): void {
            $table->jsonb('attribution')->nullable();
        });

        DB::statement("ALTER TABLE report_deliveries ADD CONSTRAINT report_deliveries_channel_check CHECK (channel IN ('email', 'push'))");
        DB::statement("ALTER TABLE report_deliveries ADD CONSTRAINT report_deliveries_status_check CHECK (status IN ('pending', 'sent', 'failed', 'skipped'))");
    }

    public function down(): void
    {
        Schema::table('enquiries', fn (Blueprint $table) => $table->dropColumn('attribution'));
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('attribution'));
        Schema::dropIfExists('report_deliveries');
        Schema::dropIfExists('daily_reports');
    }
};
