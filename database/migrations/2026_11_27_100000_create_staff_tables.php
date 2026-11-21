<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Employees, rosters and attendance (FINAL_SPECIFICATION §17).
 *
 * - employees: profile linked to a login; staff propose changes, a manager approves (EMP-01).
 *   Phone and emergency contact are encrypted. No biometric data is ever stored (DEC-07).
 * - shifts, roster_patterns, roster_changes, public_holidays, leave_requests: who works when (EMP-02).
 * - attendance_punches: raw clock events, never edited, deduplicated by a composite key (ATT-02).
 * - attendance_days: derived per employee per day, with audited corrections (ATT-03/04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestampsTz();
        });

        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_number', 32)->nullable()->unique();
            $table->string('full_name');
            $table->string('preferred_name')->nullable();
            $table->string('job_title')->nullable();
            $table->text('phone')->nullable();
            $table->text('emergency_contact_name')->nullable();
            $table->text('emergency_contact_phone')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('device_person_id', 64)->nullable()->unique();
            $table->string('status', 16)->default('pending');
            $table->jsonb('pending_changes')->nullable();
            $table->timestampTz('changes_submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestampsTz();
        });

        Schema::create('shifts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('clock_in_opens_minutes')->default(60);
            $table->unsignedSmallInteger('late_after_minutes')->default(0);
            $table->unsignedSmallInteger('clock_out_closes_minutes')->default(180);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestampsTz();
        });

        Schema::create('roster_patterns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('weekday');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('works_public_holidays')->default(true);
            $table->timestampsTz();

            $table->index(['employee_id', 'weekday']);
        });

        Schema::create('roster_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('covering_for_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['employee_id', 'work_date']);
        });

        Schema::create('public_holidays', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->timestampsTz();
        });

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 16)->default('pending');
            $table->text('reason')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();

            $table->index(['employee_id', 'starts_on']);
        });

        Schema::create('attendance_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 24);
            $table->string('filename')->nullable();
            $table->unsignedInteger('rows_read')->default(0);
            $table->unsignedInteger('rows_added')->default(0);
            $table->unsignedInteger('rows_duplicate')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);
            $table->jsonb('errors')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('attendance_punches', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 24);
            $table->string('device_serial', 64)->nullable();
            $table->string('device_person_id', 64)->nullable();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('punched_at');
            $table->string('direction', 8)->default('unknown');
            $table->string('verify_mode', 32)->nullable();
            $table->string('dedup_key', 64)->unique();
            $table->foreignId('attendance_import_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->jsonb('raw')->nullable();
            $table->timestampsTz();

            $table->index(['employee_id', 'punched_at']);
            $table->index(['device_person_id', 'punched_at']);
        });

        Schema::create('attendance_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('scheduled_start')->nullable();
            $table->timestampTz('scheduled_end')->nullable();
            $table->timestampTz('first_in')->nullable();
            $table->timestampTz('last_out')->nullable();
            $table->string('status', 16);
            $table->unsignedInteger('minutes_worked')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->boolean('is_corrected')->default(false);
            $table->text('correction_reason')->nullable();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('corrected_at')->nullable();
            $table->timestampsTz();

            $table->unique(['employee_id', 'work_date']);
            $table->index(['work_date', 'status']);
        });

        DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_status_check CHECK (status IN ('pending', 'active', 'inactive'))");
        DB::statement('ALTER TABLE roster_patterns ADD CONSTRAINT roster_patterns_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE roster_patterns ADD CONSTRAINT roster_patterns_dates_check CHECK (effective_until IS NULL OR effective_until >= effective_from)');
        DB::statement('ALTER TABLE leave_requests ADD CONSTRAINT leave_requests_dates_check CHECK (ends_on >= starts_on)');
        DB::statement("ALTER TABLE attendance_punches ADD CONSTRAINT attendance_punches_direction_check CHECK (direction IN ('in', 'out', 'unknown'))");
        DB::statement("ALTER TABLE attendance_punches ADD CONSTRAINT attendance_punches_manual_reason CHECK (source <> 'manual' OR (reason IS NOT NULL AND entered_by IS NOT NULL))");
        DB::statement('ALTER TABLE attendance_days ADD CONSTRAINT attendance_days_correction_reason CHECK (NOT is_corrected OR (correction_reason IS NOT NULL AND corrected_by IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_days');
        Schema::dropIfExists('attendance_punches');
        Schema::dropIfExists('attendance_imports');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('public_holidays');
        Schema::dropIfExists('roster_changes');
        Schema::dropIfExists('roster_patterns');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('departments');
    }
};
