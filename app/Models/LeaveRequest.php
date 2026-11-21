<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Leave (EMP-02). Approved leave makes the employee off duty for those days. No leave balances
 * or pay are calculated (DEC-09).
 */
#[Fillable(['employee_id', 'type', 'starts_on', 'ends_on', 'status', 'reason', 'requested_by'])]
class LeaveRequest extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'type' => LeaveType::class,
            'status' => LeaveStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('people')->logOnly(['type', 'starts_on', 'ends_on', 'status', 'decided_by'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function days(): int
    {
        return (int) $this->starts_on->diffInDays($this->ends_on) + 1;
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
