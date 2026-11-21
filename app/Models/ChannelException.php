<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The exception queue (DEC-02, CHN-03): something from a channel that could not be handled
 * safely by itself and needs a person — no free room, unknown room code, a change of dates,
 * or an iCal booking that clashes with one of ours.
 */
#[Fillable(['channel_id', 'channel_reservation_id', 'room_id', 'kind', 'summary', 'details', 'status'])]
class ChannelException extends Model
{
    use LogsActivity;

    public const NO_ROOM = 'no_room';

    public const UNMAPPED = 'unmapped';

    public const MODIFIED = 'modified';

    public const CANCEL_FAILED = 'cancel_failed';

    public const ICAL_CLASH = 'ical_clash';

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('channels')->logOnly(['status', 'resolution'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            self::NO_ROOM => 'No room free',
            self::UNMAPPED => 'Unknown room code',
            self::MODIFIED => 'Changed by the guest',
            self::CANCEL_FAILED => 'Cancellation needs checking',
            self::ICAL_CLASH => 'Calendar clash',
            default => ucfirst(str_replace('_', ' ', $this->kind)),
        };
    }

    /**
     * @param  Builder<ChannelException>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', 'open');
    }

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * @return BelongsTo<ChannelReservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ChannelReservation::class, 'channel_reservation_id');
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
