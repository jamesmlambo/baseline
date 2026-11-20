<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra on a booking, with the price frozen at the time it was added.
 */
#[Fillable(['booking_id', 'extra_id', 'description', 'quantity', 'unit_price_cents', 'total_cents'])]
class BookingExtra extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => MoneyCast::class,
            'total_cents' => MoneyCast::class,
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Extra, $this>
     */
    public function extra(): BelongsTo
    {
        return $this->belongsTo(Extra::class);
    }
}
