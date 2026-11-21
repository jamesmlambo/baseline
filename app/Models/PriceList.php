<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\StayDates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A secure, expiring WhatsApp price-list link (WA-01). It shows live availability and prices;
 * opening it never holds a room (WA-03).
 */
#[Fillable([
    'token', 'arrival_date', 'departure_date', 'adults', 'children', 'customer_name', 'customer_phone', 'expires_at',
    'revoked_at', 'first_opened_at', 'open_count', 'booking_id', 'followed_up_at', 'created_by',
])]
#[Hidden(['token'])]
class PriceList extends Model
{
    protected function casts(): array
    {
        return [
            'arrival_date' => 'immutable_date',
            'departure_date' => 'immutable_date',
            'adults' => 'integer',
            'children' => 'integer',
            'customer_phone' => 'encrypted',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'first_opened_at' => 'immutable_datetime',
            'followed_up_at' => 'immutable_datetime',
            'open_count' => 'integer',
        ];
    }

    public function stay(): StayDates
    {
        return new StayDates($this->arrival_date, $this->departure_date);
    }

    public function isUsable(): bool
    {
        $arrivalStillAhead = $this->arrival_date->isToday() || $this->arrival_date->isFuture();

        return $this->revoked_at === null && $this->expires_at->isFuture() && $arrivalStillAhead;
    }

    public function url(): string
    {
        return route('price-list.show', $this->token);
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
