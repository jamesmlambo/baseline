<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LostPropertyStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Lost-property log (HK-10). Front desk and managers only.
 */
#[Fillable(['found_on', 'found_at', 'room_id', 'booking_id', 'description', 'storage_location', 'found_by', 'status', 'outcome_notes', 'resolved_at', 'resolved_by'])]
class LostPropertyItem extends Model
{
    use LogsActivity;

    /**
     * Closing an entry records who did it and when; reopening clears that.
     */
    protected static function booted(): void
    {
        static::saving(function (LostPropertyItem $item): void {
            if ($item->status === LostPropertyStatus::Held) {
                $item->resolved_at = null;
                $item->resolved_by = null;
            } elseif ($item->resolved_at === null) {
                $item->resolved_at = now();
                $item->resolved_by = auth()->id();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'found_on' => 'date',
            'status' => LostPropertyStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('lost_property')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
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
    public function finder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'found_by');
    }
}
