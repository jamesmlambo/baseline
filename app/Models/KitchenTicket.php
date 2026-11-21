<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KitchenTicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An order for the kitchen (LSR-04), from a room-service order or a meal booking.
 * Items are a list of {name, quantity}; allergens are copied from the menu at order time.
 */
#[Fillable(['guest_request_id', 'service_booking_id', 'room_id', 'for_label', 'items', 'allergens', 'notes', 'wanted_at', 'status'])]
class KitchenTicket extends Model
{
    protected function casts(): array
    {
        return [
            'items' => 'array',
            'status' => KitchenTicketStatus::class,
            'wanted_at' => 'immutable_datetime',
            'preparing_at' => 'datetime',
            'ready_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<KitchenTicket>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [KitchenTicketStatus::New, KitchenTicketStatus::Preparing, KitchenTicketStatus::Ready]);
    }

    /**
     * @return BelongsTo<GuestRequest, $this>
     */
    public function guestRequest(): BelongsTo
    {
        return $this->belongsTo(GuestRequest::class);
    }

    /**
     * @return BelongsTo<ServiceBooking, $this>
     */
    public function serviceBooking(): BelongsTo
    {
        return $this->belongsTo(ServiceBooking::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
