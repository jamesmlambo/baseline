<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A reservation. Status changes go through BookingStateMachine only, never direct updates.
 */
#[Fillable([
    'reference', 'status', 'payment_status', 'source', 'external_reference',
    'arrival_date', 'departure_date', 'lead_guest_name', 'lead_guest_email', 'lead_guest_phone',
    'hold_expires_at', 'total_cents', 'special_requests', 'created_by',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'source' => BookingSource::class,
            'arrival_date' => 'immutable_date',
            'departure_date' => 'immutable_date',
            'hold_expires_at' => 'immutable_datetime',
            'total_cents' => MoneyCast::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('bookings')
            ->logOnly([
                'status', 'payment_status', 'arrival_date', 'departure_date',
                'lead_guest_name', 'lead_guest_email', 'lead_guest_phone', 'hold_expires_at', 'total_cents',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return HasMany<BookingRoom, $this>
     */
    public function bookingRooms(): HasMany
    {
        return $this->hasMany(BookingRoom::class);
    }

    /**
     * @return HasMany<BookingRoom, $this>
     */
    public function activeBookingRooms(): HasMany
    {
        return $this->bookingRooms()->whereNull('released_at');
    }

    /**
     * @return HasMany<BookingStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function nights(): int
    {
        return (int) $this->arrival_date->diffInDays($this->departure_date);
    }
}
