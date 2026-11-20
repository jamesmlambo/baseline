<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Enums\PaymentRecordStatus;
use App\Exceptions\RoomUnavailableException;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use App\Services\Bookings\BookingStateMachine;
use App\Services\Inventory\RoomNightLedger;
use App\Services\Payments\PaymentLedger;
use App\Support\StayDates;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * PEND-02 interim rule: when payment arrives after a hold expired, re-check inventory.
 * If the same room (or another of the same type) is still free, claim it and confirm;
 * otherwise nothing changes and staff refund instead.
 */
class ReinstateBooking
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly RoomNightLedger $ledger,
        private readonly BookingStateMachine $stateMachine,
        private readonly PaymentLedger $payments,
    ) {}

    public function handle(Booking $booking, User $by): Booking
    {
        if ($booking->status !== BookingStatus::Expired) {
            throw new InvalidArgumentException('Only expired bookings can be reinstated.');
        }

        $previous = $booking->bookingRooms()->where('release_reason', BookingStatus::Expired->value)->with('room', 'accommodationType')->get();

        if ($previous->isEmpty()) {
            throw new InvalidArgumentException('This booking has no rooms to reinstate.');
        }

        $stay = new StayDates($booking->arrival_date, $booking->departure_date);

        DB::transaction(function () use ($booking, $previous, $stay, $by): void {
            foreach ($previous as $old) {
                /** @var BookingRoom $old */
                $candidates = collect([$old->room])->merge(
                    $this->availability->availableRooms($old->accommodationType, $stay)->reject(fn ($room) => $room->id === $old->room_id),
                );

                $claimed = false;

                foreach ($candidates as $room) {
                    try {
                        DB::transaction(function () use ($old, $room, $booking, $stay): void {
                            $replacement = BookingRoom::query()->create([
                                'booking_id' => $booking->id,
                                'accommodation_type_id' => $old->accommodation_type_id,
                                'room_id' => $room->id,
                                'arrival_date' => $stay->arrivalString(),
                                'departure_date' => $stay->departureString(),
                                'adults' => $old->adults,
                                'children' => $old->children,
                                'accommodation_total_cents' => $old->accommodation_total_cents,
                            ]);

                            $this->ledger->claimForBookingRoom($replacement, $stay);
                        });

                        $claimed = true;

                        break;
                    } catch (QueryException $exception) {
                        if (! RoomNightLedger::isConflict($exception)) {
                            throw $exception;
                        }
                    }
                }

                if (! $claimed) {
                    throw RoomUnavailableException::forType($old->accommodationType->name);
                }
            }

            $this->stateMachine->transition($booking, BookingStatus::Confirmed, 'Reinstated after late payment', $by);

            $booking->payments()
                ->where('status', PaymentRecordStatus::Succeeded)
                ->whereNotNull('exception_reason')
                ->get()
                ->each(fn ($payment) => $this->payments->resolveException($payment));

            $this->payments->sync($booking);
        });

        return $booking->refresh();
    }
}
