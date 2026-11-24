<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring tasks can repeat every N days, weeks or months, from a first date, optionally until
 * an end date (HK-08). Existing schedules keep repeating every N days from their next due date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $table): void {
            $table->renameColumn('interval_days', 'repeat_every');
        });

        Schema::table('maintenance_schedules', function (Blueprint $table): void {
            $table->string('repeat_unit', 8)->default('day')->after('repeat_every');
            $table->date('starts_on')->nullable()->after('repeat_unit');
            $table->date('ends_on')->nullable()->after('next_due_on');
            $table->time('due_time')->nullable()->after('ends_on');
            $table->string('priority', 16)->nullable()->after('assigned_to');
            $table->text('notes')->nullable()->after('checklist');
        });

        DB::table('maintenance_schedules')->whereNull('starts_on')->update(['starts_on' => DB::raw('next_due_on')]);

        Schema::table('maintenance_schedules', function (Blueprint $table): void {
            $table->date('starts_on')->nullable(false)->change();
        });

        DB::statement('ALTER TABLE maintenance_schedules ADD CONSTRAINT maintenance_schedules_repeat_valid CHECK (repeat_every >= 1 AND repeat_unit IN (\'day\', \'week\', \'month\'))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE maintenance_schedules DROP CONSTRAINT IF EXISTS maintenance_schedules_repeat_valid');

        Schema::table('maintenance_schedules', function (Blueprint $table): void {
            $table->dropColumn(['repeat_unit', 'starts_on', 'ends_on', 'due_time', 'priority', 'notes']);
        });

        Schema::table('maintenance_schedules', function (Blueprint $table): void {
            $table->renameColumn('repeat_every', 'interval_days');
        });
    }
};
