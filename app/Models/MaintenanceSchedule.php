<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Preventive work that repeats every N days (HK-08), e.g. pool chemicals or pump service.
 */
#[Fillable(['title', 'task_type', 'asset_id', 'room_id', 'location', 'interval_days', 'next_due_on', 'assigned_to', 'checklist', 'is_active'])]
class MaintenanceSchedule extends Model
{
    use LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'task_type' => TaskType::class,
            'interval_days' => 'integer',
            'next_due_on' => 'date',
            'checklist' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('maintenance')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
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
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
