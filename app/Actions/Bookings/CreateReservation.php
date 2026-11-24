<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Actions\Bookings\Data\BookingRequest;
use App\Actions\Bookings\Data\ReservationRequest;
use App\Actions\Bookings\Data\RoomRequest;
use App\Enums\BookingStatus;
use App\Exceptions\RoomUnavailableException;
use App\Models\Booking;
use App\Models\BookingExtra;
use App\Models\Extra;
use App\Models\Guest;
use App\Services\Finance\DocumentIssuer;
use App\Services\Finance\FinanceSettings;
use App\Services\Messaging\BookingMessages;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a reservation with its guest profile, extras and invoice in one transaction
 * (§8 online booking, REC-03 front-desk booking). The room itself is assigned by PlaceBooking.
 */
class CreateReservation
{
    public function __construct(
        private readonly PlaceBooking $placeBooking,
        private readonly DocumentIssuer $documents,
        private readonly FinanceSettings $finance,
        private readonly BookingMessages $messages,
    ) {}

    /**
     * @throws RoomUnavailableException
     */
    public function handle(ReservationRequest $request): Booking
    {
        return DB::transaction(function () use ($request): Booking {
            $guest = $this->resolveGuest($request);

            $booking = $this->placeBooking->handle(new BookingRequest(
                stay: $request->stay,
                rooms: [new RoomRequest($request->type, $request->adults, $request->children, $request->room), ...$request->additionalRooms],
                leadGuestName: $guest->fullName(),
                source: $request->source,
                status: $request->status,
                leadGuestEmail: Guest::normaliseEmail($request->email),
                leadGuestPhone: Guest::normalisePhone($request->phone),
                holdExpiresAt: $request->status === BookingStatus::Hold ? ($request->holdExpiresAt ?? $this->holdExpiry()) : null,
                specialRequests: $request->specialRequests,
                createdBy: $request->createdBy,
            ));

            $extrasTotal = $this->addExtras($booking, $request)->add($this->addChargeLines($booking, $request));
            $total = $booking->total_cents->add($extrasTotal);

            $booking->forceFill([
                'guest_id' => $guest->id,
                'public_token' => Str::random(48),
                'total_cents' => $total,
                'deposit_due_cents' => $this->finance->depositFor($total),
                'preferred_payment_method' => $request->preferredPaymentMethod,
                'terms_accepted_at' => $request->termsAccepted ? now() : null,
            ])->save();

            $this->documents->invoiceForBooking($booking, $request->createdBy);
            $booking->refresh()->load(['activeBookingRooms.room', 'extras', 'guest']);

            // WA-04 / MSG-03: tell the guest how to pay, once the booking is safely saved.
            if ($booking->status === BookingStatus::Hold) {
                DB::afterCommit(fn () => $this->messages->bookingReceived($booking));
            }

            return $booking;
        });
    }

    /**
     * Reuse the existing profile of a returning guest (BOOK-06), matched by email or phone.
     */
    private function resolveGuest(ReservationRequest $request): Guest
    {
        $guest = Guest::query()->matchingContact($request->email, $request->phone)->oldest('id')->first()
            ?? new Guest(['first_name' => $request->firstName, 'last_name' => $request->lastName]);

        $guest->email ??= Guest::normaliseEmail($request->email);
        $guest->phone ??= Guest::normalisePhone($request->phone);

        if ($request->marketingConsent && ! $guest->marketing_consent) {
            $guest->marketing_consent = true;
            $guest->marketing_consent_at = now();
        }

        $guest->save();

        return $guest;
    }

    private function addExtras(Booking $booking, ReservationRequest $request): Money
    {
        $total = Money::zero();

        if ($request->extraQuantities === []) {
            return $total;
        }

        $extras = Extra::query()->where('is_active', true)->whereIn('id', array_keys($request->extraQuantities))->get();

        foreach ($extras as $extra) {
            $requested = (int) ($request->extraQuantities[$extra->id] ?? 0);
            $quantity = $requested > 0 ? $requested : $extra->pricing_unit->quantityFor($booking->nights(), $request->guestCount());
            $lineTotal = $extra->price_cents->multiply($quantity);

            BookingExtra::query()->create([
                'booking_id' => $booking->id,
                'extra_id' => $extra->id,
                'description' => sprintf('%s (%s)', $extra->name, $extra->pricing_unit->label()),
                'quantity' => $quantity,
                'unit_price_cents' => $extra->price_cents,
                'total_cents' => $lineTotal,
            ]);

            $total = $total->add($lineTotal);
        }

        return $total;
    }

    /**
     * Priced items that are not extras (e.g. spa or day passes from a quotation), billed on the stay invoice.
     */
    private function addChargeLines(Booking $booking, ReservationRequest $request): Money
    {
        $total = Money::zero();

        foreach ($request->chargeLines as $line) {
            $lineTotal = $line['unit']->multiply($line['quantity']);

            BookingExtra::query()->create([
                'booking_id' => $booking->id,
                'extra_id' => null,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price_cents' => $line['unit'],
                'total_cents' => $lineTotal,
            ]);

            $total = $total->add($lineTotal);
        }

        return $total;
    }

    private function holdExpiry(): CarbonImmutable
    {
        return now()->toImmutable()->addMinutes($this->finance->onlineHoldMinutes());
    }
}
