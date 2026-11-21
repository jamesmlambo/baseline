<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A job for housekeeping, gardening or maintenance (HK-01/02). Created by staff, by checkout
 * (CHK-03), by the Monday deep-clean run (HK-04), by schedules (HK-08) or as rework (HK-05).
 *
 * Checklist items are stored as a list of {label, done}.
 */
#[Fillable([
    'type', 'title', 'source', 'idempotency_key', 'room_id', 'asset_id', 'location', 'booking_id',
    'parent_task_id', 'maintenance_schedule_id', 'assigned_to', 'assigned_by', 'created_by', 'priority',
    'status', 'due_at', 'estimated_minutes', 'checklist', 'notes', 'completion_notes', 'cost_cents', 'supplier',
])]
class Task extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_CHECKOUT = 'checkout';

    public const SOURCE_DEEP_CLEAN = 'deep_clean';

    public const SOURCE_SCHEDULE = 'schedule';

    public const SOURCE_REWORK = 'rework';

    public const SOURCE_FAULT = 'fault';

    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'checklist' => 'array',
            'cost_cents' => MoneyCast::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('housekeeping')
            ->logOnly(['status', 'assigned_to', 'priority', 'due_at', 'completion_notes', 'cost_cents'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')->useDisk('local')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function locationLabel(): string
    {
        return $this->room?->name ?? $this->asset?->name ?? $this->location ?? '—';
    }

    /**
     * @return list<array{label: string, done: bool}>
     */
    public function checklistItems(): array
    {
        return array_values(array_map(
            fn (mixed $item): array => is_array($item)
                ? ['label' => (string) ($item['label'] ?? ''), 'done' => (bool) ($item['done'] ?? false)]
                : ['label' => (string) $item, 'done' => false],
            $this->checklist ?? [],
        ));
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [TaskStatus::Open, TaskStatus::InProgress]);
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeAssignedTo(Builder $query, User $user): void
    {
        $query->where('assigned_to', $user->id);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function reworks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    /**
     * @return HasOne<Inspection, $this>
     */
    public function inspection(): HasOne
    {
        return $this->hasOne(Inspection::class);
    }
}
