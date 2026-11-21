<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A one-day exception to the weekly roster (EMP-02): a different shift, a day off (no shift),
 * or covering for a colleague.
 */
#[Fillable(['employee_id', 'work_date', 'shift_id', 'covering_for_employee_id', 'reason', 'created_by'])]
class RosterChange extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['work_date' => 'date'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('people')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
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
     * @return BelongsTo<Employee, $this>
     */
    public function coveringFor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'covering_for_employee_id');
    }
}
