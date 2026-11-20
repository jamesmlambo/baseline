<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One room claimed for one night: the unit of inventory (INV-03).
 * Only one unreleased claim may exist per (room, night).
 */
#[Fillable(['room_id', 'night_date', 'booking_room_id', 'room_block_id', 'released_at'])]
class RoomNight extends Model
{
    protected function casts(): array
    {
        return [
            'night_date' => 'immutable_date',
            'released_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<BookingRoom, $this>
     */
    public function bookingRoom(): BelongsTo
    {
        return $this->belongsTo(BookingRoom::class);
    }

    /**
     * @return BelongsTo<RoomBlock, $this>
     */
    public function roomBlock(): BelongsTo
    {
        return $this->belongsTo(RoomBlock::class);
    }

    /**
     * @param  Builder<RoomNight>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('released_at');
    }
}
