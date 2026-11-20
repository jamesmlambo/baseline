<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Exceptions\RoomUnavailableException;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\User;
use App\Services\Inventory\RoomNightLedger;
use App\Support\StayDates;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Moves a booking to another physical room (INV-07). Needs a reason, is audited, and keeps the
 * old assignment as released history. Guest-portal and housekeeping updates hook in here in M7/M8.
 */
class ReassignRoom
{
    public function __construct(private readonly RoomNightLedger $ledger) {}

    public function handle(BookingRoom $current, Room $newRoom, string $reason, ?User $by = null): BookingRoom
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required to move a booking to another room.');
        }

        if ($current->released_at !== null) {
            throw new InvalidArgumentException('This room assignment has already been released.');
        }

        if (! $current->booking->status->holdsInventory()) {
            throw new InvalidArgumentException('Only active bookings can be moved.');
        }

        if ($newRoom->id === $current->room_id) {
            throw new InvalidArgumentException('The booking is already in that room.');
        }

        try {
            return DB::transaction(function () use ($current, $newRoom, $reason, $by): BookingRoom {
                $this->ledger->releaseBookingRoom($current, 'reassigned');

                $replacement = BookingRoom::query()->create([
                    'booking_id' => $current->booking_id,
                    'accommodation_type_id' => $newRoom->accommodation_type_id,
                    'room_id' => $newRoom->id,
                    'arrival_date' => $current->arrival_date->toDateString(),
                    'departure_date' => $current->departure_date->toDateString(),
                    'adults' => $current->adults,
                    'children' => $current->children,
                    'accommodation_total_cents' => $current->accommodation_total_cents,
                ]);

                $this->ledger->claimForBookingRoom($replacement, new StayDates($current->arrival_date, $current->departure_date));

                activity('room_assignments')
                    ->performedOn($current->booking)
                    ->causedBy($by)
                    ->event('room_reassigned')
                    ->withProperties([
                        'from_room_id' => $current->room_id,
                        'to_room_id' => $newRoom->id,
                        'reason' => $reason,
                    ])
                    ->log('Booking moved to another room');

                return $replacement;
            });
        } catch (QueryException $exception) {
            // The transaction rolled back; drop the in-memory "released" change too.
            $current->refresh();

            if (RoomNightLedger::isConflict($exception)) {
                throw RoomUnavailableException::forRoom($newRoom->name);
            }

            throw $exception;
        }
    }
}
