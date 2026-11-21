<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps a channel's room code to one of our room types, so imported reservations land correctly.
 */
#[Fillable(['channel_id', 'accommodation_type_id', 'external_code'])]
class ChannelRoomType extends Model
{
    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * @return BelongsTo<AccommodationType, $this>
     */
    public function accommodationType(): BelongsTo
    {
        return $this->belongsTo(AccommodationType::class);
    }
}
