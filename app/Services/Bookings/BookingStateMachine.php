<?php

declare(strict_types=1);

namespace App\Services\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\InvalidBookingTransitionException;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingStatusHistory;
use App\Models\User;
use App\Services\Inventory\RoomNightLedger;
use Illuminate\Support\Facades\DB;

/**
 * The only way to change a booking's status (BOOK-03). Rejects invalid transitions,
 * records history, and releases room nights when a booking is cancelled, expires or
 * becomes a no-show.
 */
class BookingStateMachine
{
    public function __construct(private readonly RoomNightLedger $ledger) {}

    /**
     * @throws InvalidBookingTransitionException
     */
    public function transition(Booking $booking, BookingStatus $to, ?string $reason = null, ?User $by = null): Booking
    {
        return DB::transaction(function () use ($booking, $to, $reason, $by): Booking {
            /** @var Booking $locked */
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw InvalidBookingTransitionException::between($from, $to);
            }

            $locked->status = $to;
            $locked->save();

            if ($to->releasesInventory()) {
                $locked->activeBookingRooms()->get()->each(
                    fn (BookingRoom $bookingRoom) => $this->ledger->releaseBookingRoom($bookingRoom, $to->value),
                );
            }

            BookingStatusHistory::query()->create([
                'booking_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $to,
                'reason' => $reason,
                'changed_by' => $by?->id,
            ]);

            $booking->setRawAttributes($locked->getAttributes(), true);

            return $booking;
        });
    }
}
