<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Attendance for one employee on one work date, derived from the roster and punches (ATT-03).
 * A corrected day is never recalculated automatically (ATT-04).
 */
#[Fillable(['employee_id', 'work_date', 'shift_id', 'scheduled_start', 'scheduled_end', 'first_in', 'last_out', 'status', 'minutes_worked', 'late_minutes'])]
class AttendanceDay extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'scheduled_start' => 'immutable_datetime',
            'scheduled_end' => 'immutable_datetime',
            'first_in' => 'immutable_datetime',
            'last_out' => 'immutable_datetime',
            'status' => AttendanceStatus::class,
            'minutes_worked' => 'integer',
            'late_minutes' => 'integer',
            'is_corrected' => 'boolean',
            'corrected_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('attendance')
            ->logOnly(['status', 'first_in', 'last_out', 'minutes_worked', 'is_corrected', 'correction_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function hoursLabel(): string
    {
        return sprintf('%d:%02d', intdiv($this->minutes_worked, 60), $this->minutes_worked % 60);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
