<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An event read from a channel's iCal feed, and the room block it became.
 */
#[Fillable(['ical_link_id', 'uid', 'starts_on', 'ends_on', 'summary', 'room_block_id', 'removed_at'])]
class IcalEvent extends Model
{
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'removed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RoomBlock, $this>
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(RoomBlock::class, 'room_block_id');
    }
}
