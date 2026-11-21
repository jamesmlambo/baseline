<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\GuestRequestStatus;
use App\Enums\GuestRequestType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Something a guest asked for through the portal: towels, toiletries, help, a meal or checkout (GST-07).
 */
#[Fillable([
    'booking_id', 'room_id', 'type', 'status', 'details', 'items', 'total_cents', 'charge_to_room', 'invoice_id',
    'acknowledged_by', 'acknowledged_at', 'completed_by', 'completed_at', 'escalation_level',
])]
class GuestRequest extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'type' => GuestRequestType::class,
            'status' => GuestRequestStatus::class,
            'items' => 'array',
            'total_cents' => MoneyCast::class,
            'charge_to_room' => 'boolean',
            'acknowledged_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'escalation_level' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('guest_requests')->logOnly(['status', 'acknowledged_by', 'completed_by', 'escalation_level'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @param  Builder<GuestRequest>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [GuestRequestStatus::New, GuestRequestStatus::Acknowledged]);
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
}
