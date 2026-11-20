<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 64);
            $table->string('key', 128)->unique();
            $table->string('label');
            $table->jsonb('value')->nullable();
            $table->string('type', 32)->default('string');
            $table->boolean('is_pending_client_decision')->default(false);
            $table->string('decision_reference', 16)->nullable();
            $table->text('help_text')->nullable();
            $table->timestampsTz();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
