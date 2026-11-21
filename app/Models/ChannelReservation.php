<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every reservation a channel has sent us, once per channel + reference (CHN-03). The channel's
 * own total is kept for reconciliation; our booking is priced by our rate engine.
 */
#[Fillable(['channel_id', 'external_reference', 'booking_id', 'status', 'channel_total_cents', 'payload', 'last_seen_at'])]
class ChannelReservation extends Model
{
    public const BOOKED = 'booked';

    public const MODIFIED = 'modified';

    public const CANCELLED = 'cancelled';

    public const EXCEPTION = 'exception';

    protected function casts(): array
    {
        return [
            'channel_total_cents' => MoneyCast::class,
            'payload' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
