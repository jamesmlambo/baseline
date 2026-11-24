<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Actions\Bookings\Data\ReservationRequest;
use App\Actions\Bookings\Data\RoomRequest;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Exceptions\RoomUnavailableException;
use App\Models\AccommodationType;
use App\Models\Booking;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Finance\QuoteBuilder;
use App\Support\Money;
use App\Support\StayDates;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Turns an accepted quotation into a booking (PAY-04): the quoted rooms (all on one booking),
 * extras, and services such as spa or day passes, which go on the invoice for staff to give a
 * time in Leisure. Rooms are checked and held like any front-desk booking; prices are today's,
 * so staff are warned first when they differ from the quote (see priceChange()).
 */
class BookQuotation
{
    public function __construct(
        private readonly CreateReservation $reservations,
        private readonly QuoteBuilder $quotes,
    ) {}

    /**
     * @param  array{first_name: string, last_name: string, email?: string|null, phone?: string|null}  $guest
     *
     * @throws RoomUnavailableException
     */
    public function handle(Quotation $quotation, array $guest, BookingSource $source, CarbonImmutable $holdUntil, User $by): Booking
    {
        if (! $by->can('book', $quotation)) {
            throw new AuthorizationException('You cannot book this quotation.');
        }

        if ($holdUntil->isPast()) {
            throw new InvalidArgumentException('Hold the rooms until a time in the future.');
        }

        return DB::transaction(function () use ($quotation, $guest, $source, $holdUntil, $by): Booking {
            /** @var Quotation $locked */
            $locked = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();

            if (! $locked->canBeBooked()) {
                throw new InvalidArgumentException("Quotation {$locked->number} cannot be booked: it is already booked, has expired, or has no rooms.");
            }

            $items = $locked->items;
            $stay = new StayDates($items['arrival'], $items['departure']);

            if ($stay->arrival->isBefore(today())) {
                throw new InvalidArgumentException('The quoted arrival date has passed. Make a new quotation.');
            }

            $rooms = $this->roomRequests($items['rooms']);
            $first = array_shift($rooms);

            $booking = $this->reservations->handle(new ReservationRequest(
                type: $first->type,
                stay: $stay,
                adults: $first->adults,
                children: $first->children,
                firstName: trim($guest['first_name']),
                lastName: trim($guest['last_name']),
                email: $guest['email'] ?? null,
                phone: $guest['phone'] ?? null,
                source: $source,
                status: BookingStatus::Hold,
                extraQuantities: $this->extraQuantities($items['extras'] ?? []),
                specialRequests: "From quotation {$locked->number}.",
                holdExpiresAt: $holdUntil,
                createdBy: $by,
                additionalRooms: $rooms,
                chargeLines: $this->serviceLines($items['services'] ?? []),
            ));

            $locked->forceFill(['booking_id' => $booking->id])->save();

            activity('finance')->performedOn($locked)->causedBy($by)->log("Quotation {$locked->number} booked as {$booking->reference}");

            return $booking;
        });
    }

    /**
     * Today's total for the quoted items, when it differs from the quotation; null when unchanged.
     */
    public function priceChange(Quotation $quotation): ?Money
    {
        if ($quotation->items === null) {
            return null;
        }

        try {
            $current = QuoteBuilder::total($this->quotes->linesForItems($quotation->items));
        } catch (InvalidArgumentException) {
            return null;
        }

        return $current->equals($quotation->total_cents) ? null : $current;
    }

    /**
     * @param  list<array{accommodation_type_id: int, rooms: int, adults: int, children: int}>  $rows
     * @return list<RoomRequest>
     */
    private function roomRequests(array $rows): array
    {
        $requests = [];

        foreach ($rows as $row) {
            $type = AccommodationType::query()->findOrFail($row['accommodation_type_id']);

            for ($i = 0; $i < max(1, (int) $row['rooms']); $i++) {
                $requests[] = new RoomRequest($type, (int) $row['adults'], (int) $row['children']);
            }
        }

        return $requests;
    }

    /**
     * @param  list<array{extra_id: int, quantity: int|null}>  $rows
     * @return array<int, int>
     */
    private function extraQuantities(array $rows): array
    {
        $quantities = [];

        foreach ($rows as $row) {
            $quantities[(int) $row['extra_id']] = ($quantities[(int) $row['extra_id']] ?? 0) + (int) ($row['quantity'] ?? 0);
        }

        return $quantities;
    }

    /**
     * @param  list<array{service_id: int, people: int, sessions: int}>  $rows
     * @return list<array{description: string, quantity: int, unit: Money}>
     */
    private function serviceLines(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        return array_map(fn (array $line): array => [
            'description' => $line['description'].' (time to be arranged)',
            'quantity' => $line['quantity'],
            'unit' => $line['unit'],
        ], $this->quotes->lines(null, [], [], $rows));
    }
}
