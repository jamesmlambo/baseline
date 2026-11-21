<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Housekeeping, inspections and maintenance (FINAL_SPECIFICATION §15–16).
 *
 * - rooms.cleaning_status: Dirty → Cleaning → Awaiting inspection → Ready (HK-06).
 * - tasks: every job for housekeeping, gardening and maintenance (HK-01/02), with an
 *   idempotency key so checkout and schedules never create duplicates (CHK-04).
 * - inspections + inspection_scores: scored checks; a failure creates rework (HK-05).
 * - task_checklist_templates, cleaning_estimates: configurable checklists and effort (HK-04/07).
 * - assets, maintenance_schedules: asset history, warranties, preventive work (HK-08).
 * - stock_items + stock_movements: a ledger, never an editable quantity (HK-09).
 * - lost_property_items: restricted lost-property log (HK-10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->string('cleaning_status', 24)->default('ready')->after('is_active');
            $table->timestampTz('cleaning_status_changed_at')->nullable()->after('cleaning_status');
        });

        Schema::create('task_checklist_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('task_type', 40)->unique();
            $table->jsonb('items');
            $table->timestampsTz();
        });

        Schema::create('cleaning_estimates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accommodation_type_id')->constrained()->cascadeOnDelete();
            $table->string('task_type', 40);
            $table->unsignedSmallInteger('minutes');
            $table->timestampsTz();

            $table->unique(['accommodation_type_id', 'task_type']);
        });

        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('category', 64)->nullable();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('supplier')->nullable();
            $table->date('purchased_on')->nullable();
            $table->date('warranty_until')->nullable();
            $table->bigInteger('purchase_cost_cents')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('maintenance_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('task_type', 40);
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('interval_days');
            $table->date('next_due_on');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('checklist')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 40);
            $table->string('title');
            $table->string('source', 24)->default('manual');
            $table->string('idempotency_key')->nullable()->unique();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('maintenance_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 16)->default('normal');
            $table->string('status', 24)->default('open');
            $table->timestampTz('due_at')->nullable();
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->jsonb('checklist')->nullable();
            $table->text('notes')->nullable();
            $table->text('completion_notes')->nullable();
            $table->bigInteger('cost_cents')->nullable();
            $table->string('supplier')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['status', 'assigned_to']);
            $table->index(['room_id', 'status']);
        });

        Schema::create('inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('scale_max');
            $table->unsignedSmallInteger('pass_mark');
            $table->text('notes')->nullable();
            $table->timestampTz('inspected_at')->nullable();
            $table->timestampsTz();

            $table->index('status');
        });

        Schema::create('inspection_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->string('criterion');
            $table->unsignedSmallInteger('score');
            $table->boolean('passed');
            $table->text('comment')->nullable();
            $table->timestampsTz();
        });

        Schema::create('stock_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('category', 64)->nullable();
            $table->string('unit', 32)->default('each');
            $table->integer('reorder_level')->nullable();
            $table->timestampTz('low_stock_alerted_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestampsTz();
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->string('reason', 24);
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestampsTz();

            $table->index(['stock_item_id', 'created_at']);
        });

        Schema::create('lost_property_items', function (Blueprint $table): void {
            $table->id();
            $table->date('found_on');
            $table->string('found_at');
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->string('storage_location')->nullable();
            $table->foreignId('found_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('held');
            $table->text('outcome_notes')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE rooms ADD CONSTRAINT rooms_cleaning_status_check CHECK (cleaning_status IN ('dirty', 'cleaning', 'awaiting_inspection', 'ready'))");
        DB::statement('ALTER TABLE tasks ADD CONSTRAINT tasks_cost_check CHECK (cost_cents IS NULL OR cost_cents >= 0)');
        DB::statement("ALTER TABLE tasks ADD CONSTRAINT tasks_done_has_completer CHECK (status <> 'done' OR (completed_at IS NOT NULL AND completed_by IS NOT NULL))");
        DB::statement('ALTER TABLE cleaning_estimates ADD CONSTRAINT cleaning_estimates_minutes_check CHECK (minutes > 0)');
        DB::statement('ALTER TABLE maintenance_schedules ADD CONSTRAINT maintenance_schedules_interval_check CHECK (interval_days > 0)');
        DB::statement('ALTER TABLE inspections ADD CONSTRAINT inspections_scale_check CHECK (pass_mark >= 1 AND pass_mark <= scale_max)');
        DB::statement('ALTER TABLE inspection_scores ADD CONSTRAINT inspection_scores_score_check CHECK (score >= 0)');
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_quantity_check CHECK (quantity <> 0)');
        DB::statement('ALTER TABLE assets ADD CONSTRAINT assets_cost_check CHECK (purchase_cost_cents IS NULL OR purchase_cost_cents >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_property_items');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('inspection_scores');
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('cleaning_estimates');
        Schema::dropIfExists('task_checklist_templates');

        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropColumn(['cleaning_status', 'cleaning_status_changed_at']);
        });
    }
};
