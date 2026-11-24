<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every time an invoice, quotation or receipt is emailed: to which address (encrypted), when,
 * by whom and why. Shows staff whether a document was sent, where and how often.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->morphs('document');
            $table->string('channel', 16);
            $table->text('recipient');
            $table->string('reason', 32);
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('sent_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_deliveries');
    }
};
