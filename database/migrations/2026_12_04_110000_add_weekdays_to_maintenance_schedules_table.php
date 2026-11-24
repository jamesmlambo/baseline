<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly recurring tasks on chosen days (ISO 1 = Monday … 7 = Sunday), e.g. Monday, Wednesday
 * and Friday at 09:00. Empty keeps the old behaviour: every N weeks from the start date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $table): void {
            $table->jsonb('weekdays')->nullable()->after('repeat_unit');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $table): void {
            $table->dropColumn('weekdays');
        });
    }
};
