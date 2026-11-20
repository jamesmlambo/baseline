<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Financial documents and payments (FINAL_SPECIFICATION §9, PAY-01..10).
 *
 * Quotations, invoices, receipts and credit notes are separate tables with their own
 * sequential numbers. Issued documents are immutable (enforced in the models); corrections
 * use credit notes. Payments are a separate ledger; payment status never lives on documents alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->string('type', 24)->primary();
            $table->string('prefix', 8);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestampsTz();
        });

        foreach (['quotation' => 'QUO', 'invoice' => 'INV', 'receipt' => 'REC', 'credit_note' => 'CRN'] as $type => $prefix) {
            DB::table('document_sequences')->insert(['type' => $type, 'prefix' => $prefix, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        Schema::create('quotations', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 24)->unique();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name');
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('tax_cents')->default(0);
            $table->bigInteger('total_cents');
            $table->date('valid_until')->nullable();
            $table->foreignId('converted_invoice_id')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('issued_at');
            $table->timestampsTz();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 24)->unique();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name');
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('tax_cents')->default(0);
            $table->bigInteger('total_cents');
            $table->date('due_on')->nullable();
            $table->string('public_token', 64)->unique();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('issued_at');
            $table->timestampTz('voided_at')->nullable();
            $table->timestampsTz();
        });

        Schema::table('quotations', function (Blueprint $table): void {
            $table->foreign('converted_invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });

        Schema::create('document_lines', function (Blueprint $table): void {
            $table->id();
            $table->morphs('document');
            $table->string('description');
            $table->decimal('quantity', 10, 2);
            $table->bigInteger('unit_price_cents');
            $table->bigInteger('total_cents');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 24);
            $table->string('status', 32);
            $table->bigInteger('amount_cents');
            $table->bigInteger('refunded_cents')->default(0);
            $table->string('gateway', 32)->nullable();
            $table->string('gateway_reference')->nullable();
            $table->string('bank_reference')->nullable();
            $table->string('payer_reference', 32)->nullable();
            $table->text('notes')->nullable();
            $table->string('exception_reason')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampsTz();

            $table->unique(['gateway', 'gateway_reference']);
            $table->index(['status', 'created_at']);
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive CHECK (amount_cents > 0)');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_refund_within_amount CHECK (refunded_cents >= 0 AND refunded_cents <= amount_cents)');

        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway', 32);
            $table->string('event_id');
            $table->string('event_type', 64);
            $table->jsonb('payload');
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('occurred_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->string('outcome', 64)->nullable();
            $table->timestampsTz();

            $table->unique(['gateway', 'event_id']);
        });

        Schema::create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 24)->unique();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name');
            $table->bigInteger('amount_cents');
            $table->string('public_token', 64)->unique();
            $table->timestampTz('issued_at');
            $table->timestampsTz();
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_cents');
            $table->text('reason');
            $table->string('status', 24);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->string('gateway_refund_reference')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE refunds ADD CONSTRAINT refunds_amount_positive CHECK (amount_cents > 0)');
        // DEC-06: the approver must be a different person from the requester.
        DB::statement('ALTER TABLE refunds ADD CONSTRAINT refunds_dual_approval CHECK (approved_by IS NULL OR approved_by <> requested_by)');

        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 24)->unique();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name');
            $table->text('reason');
            $table->bigInteger('total_cents');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('issued_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('document_lines');
        Schema::table('quotations', fn (Blueprint $table) => $table->dropForeign(['converted_invoice_id']));
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('document_sequences');
    }
};
