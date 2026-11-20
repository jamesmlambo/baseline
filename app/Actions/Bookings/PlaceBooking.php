<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Actions\Bookings\Data\BookingRequest;
use App\Actions\Bookings\Data\RoomRequest;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\RoomUnavailableException;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingStatusHistory;
use App\Models\Room;
use App\Models\Setting;
use App\Services\Availability\AvailabilityService;
use App\Services\Inventory\RoomNightLedger;
use App\Services\Pricing\RateCalculator;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a booking and assigns a specific physical room to every requested room
 * in the same transaction (DEC-02, INV-02, INV-06, BOOK-01).
 *
 * Every booking source (website, reception, WhatsApp, channel import) must use this action.
 */
class PlaceBooking
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly RateCalculator $rates,
        private readonly RoomNightLedger $ledger,
    ) {}

    /**
     * @throws RoomUnavailableException when any requested room cannot be assigned
     */
    public function handle(BookingRequest $request): Booking
    {
        return DB::transaction(function () use ($request): Booking {
            $booking = Booking::query()->create([
                'reference' => $this->newReference(),
                'status' => $request->status,
                'payment_status' => PaymentStatus::Unpaid,
                'source' => $request->source,
                'external_reference' => $request->externalReference,
                'arrival_date' => $request->stay->arrivalString(),
                'departure_date' => $request->stay->departureString(),
                'lead_guest_name' => $request->leadGuestName,
                'lead_guest_email' => $request->leadGuestEmail,
                'lead_guest_phone' => $request->leadGuestPhone,
                'hold_expires_at' => $this->holdExpiry($request),
                'special_requests' => $request->specialRequests,
                'created_by' => $request->createdBy?->id,
                'total_cents' => 0,
            ]);

            $total = Money::zero();

            foreach ($request->rooms as $roomRequest) {
                $total = $total->add($this->assignRoom($booking, $request, $roomRequest));
            }

            $booking->forceFill(['total_cents' => $total])->save();

            BookingStatusHistory::query()->create([
                'booking_id' => $booking->id,
                'from_status' => null,
                'to_status' => $request->status,
                'reason' => 'Booking created ('.$request->source->label().')',
                'changed_by' => $request->createdBy?->id,
            ]);

            return $booking->load('activeBookingRooms.room');
        });
    }

    /**
     * Try each candidate room in its own savepoint. If PostgreSQL rejects a claim because a
     * concurrent request took the room first, move on to the next candidate.
     */
    private function assignRoom(Booking $booking, BookingRequest $request, RoomRequest $roomRequest): Money
    {
        $quote = $this->rates->quote($roomRequest->type, $request->stay, $roomRequest->adults, $roomRequest->children);

        $candidates = $roomRequest->preferredRoom !== null
            ? collect([$roomRequest->preferredRoom])
            : $this->availability->availableRooms($roomRequest->type, $request->stay);

        foreach ($candidates as $room) {
            /** @var Room $room */
            if ($room->accommodation_type_id !== $roomRequest->type->id || ! $room->is_active) {
                continue;
            }

            try {
                DB::transaction(function () use ($booking, $request, $roomRequest, $room, $quote): void {
                    $bookingRoom = BookingRoom::query()->create([
                        'booking_id' => $booking->id,
                        'accommodation_type_id' => $roomRequest->type->id,
                        'room_id' => $room->id,
                        'arrival_date' => $request->stay->arrivalString(),
                        'departure_date' => $request->stay->departureString(),
                        'adults' => $roomRequest->adults,
                        'children' => $roomRequest->children,
                        'accommodation_total_cents' => $quote->total(),
                    ]);

                    $this->ledger->claimForBookingRoom($bookingRoom, $request->stay);
                });

                return $quote->total();
            } catch (QueryException $exception) {
                if (! RoomNightLedger::isConflict($exception)) {
                    throw $exception;
                }
            }
        }

        throw $roomRequest->preferredRoom !== null
            ? RoomUnavailableException::forRoom($roomRequest->preferredRoom->name)
            : RoomUnavailableException::forType($roomRequest->type->name);
    }

    /**
     * Every hold expires (BOOK-04). Duration is PENDING CLIENT DECISION (PEND-01).
     */
    private function holdExpiry(BookingRequest $request): ?string
    {
        if ($request->status !== BookingStatus::Hold) {
            return null;
        }

        $expiry = $request->holdExpiresAt
            ?? now()->toImmutable()->addMinutes((int) Setting::get('booking.hold_minutes_online', 30));

        return $expiry->toIso8601String();
    }

    private function newReference(): string
    {
        // No 0/O/1/I so references can be read out over the phone.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $reference = 'BL-'.collect(range(1, 6))
                ->map(fn (): string => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
        } while (Booking::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
