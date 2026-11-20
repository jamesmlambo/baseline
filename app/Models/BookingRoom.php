<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The audited assignment of one physical room to a booking (INV-02, INV-07).
 */
#[Fillable([
    'booking_id', 'accommodation_type_id', 'room_id', 'arrival_date', 'departure_date',
    'adults', 'children', 'accommodation_total_cents', 'released_at', 'release_reason',
])]
class BookingRoom extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'arrival_date' => 'immutable_date',
            'departure_date' => 'immutable_date',
            'released_at' => 'immutable_datetime',
            'accommodation_total_cents' => MoneyCast::class,
            'adults' => 'integer',
            'children' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('room_assignments')
            ->logOnly(['room_id', 'arrival_date', 'departure_date', 'released_at', 'release_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<AccommodationType, $this>
     */
    public function accommodationType(): BelongsTo
    {
        return $this->belongsTo(AccommodationType::class);
    }

    /**
     * @return HasMany<RoomNight, $this>
     */
    public function roomNights(): HasMany
    {
        return $this->hasMany(RoomNight::class);
    }
}
