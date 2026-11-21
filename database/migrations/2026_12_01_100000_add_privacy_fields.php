<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRIV-03: legal holds (never anonymise while set) and a marker for anonymised guest profiles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table): void {
            $table->timestampTz('legal_hold_at')->nullable();
            $table->string('legal_hold_reason')->nullable();
            $table->timestampTz('anonymised_at')->nullable();
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->timestampTz('legal_hold_at')->nullable();
            $table->string('legal_hold_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn(['legal_hold_at', 'legal_hold_reason']));
        Schema::table('guests', fn (Blueprint $table) => $table->dropColumn(['legal_hold_at', 'legal_hold_reason', 'anonymised_at']));
    }
};
