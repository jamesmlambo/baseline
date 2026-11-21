<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InspectionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A scored check of a finished clean (HK-05). One per task; rework gets its own inspection.
 */
#[Fillable(['task_id', 'room_id', 'inspector_id', 'status', 'scale_max', 'pass_mark', 'notes', 'inspected_at'])]
class Inspection extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'status' => InspectionStatus::class,
            'scale_max' => 'integer',
            'pass_mark' => 'integer',
            'inspected_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('housekeeping')->logOnly(['status', 'inspector_id'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @param  Builder<Inspection>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', InspectionStatus::Pending);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    /**
     * @return HasMany<InspectionScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(InspectionScore::class);
    }
}
