<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoomBlockReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Takes a room out of inventory for maintenance, owner use etc. (INV-05). ends_on is exclusive.
 */
#[Fillable(['room_id', 'starts_on', 'ends_on', 'reason', 'notes', 'created_by', 'released_at', 'released_by'])]
class RoomBlock extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'released_at' => 'immutable_datetime',
            'reason' => RoomBlockReason::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventory')
            ->logOnly(['room_id', 'starts_on', 'ends_on', 'reason', 'notes', 'released_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return HasMany<RoomNight, $this>
     */
    public function roomNights(): HasMany
    {
        return $this->hasMany(RoomNight::class);
    }

    public function isActive(): bool
    {
        return $this->released_at === null;
    }
}
