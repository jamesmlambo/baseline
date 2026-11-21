<?php

declare(strict_types=1);

namespace App\Actions\Reception;

use App\Enums\BookingStatus;
use App\Exceptions\RoomUnavailableException;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\DocumentIssuer;
use App\Services\Inventory\RoomNightLedger;
use App\Services\Payments\PaymentLedger;
use App\Services\Pricing\RateCalculator;
use App\Support\StayDates;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Extends a stay in the same room (REC-03, BOOK-02). The extra nights are claimed through the
 * room-night ledger, priced by the rate engine and billed on a supplementary invoice.
 * If the room is taken on the new nights, nothing changes: move the guest first.
 */
class ExtendStay
{
    public function __construct(
        private readonly RoomNightLedger $ledger,
        private readonly RateCalculator $rates,
        private readonly DocumentIssuer $documents,
        private readonly PaymentLedger $payments,
    ) {}

    public function handle(Booking $booking, CarbonImmutable $newDeparture, User $by): Invoice
    {
        if (! $by->can('update', $booking)) {
            throw new AuthorizationException('You cannot change this booking.');
        }

        if (! in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::CheckedIn], true)) {
            throw new InvalidArgumentException('Only confirmed or checked-in bookings can be extended.');
        }

        $newDeparture = $newDeparture->startOfDay();

        if ($newDeparture->lessThanOrEqualTo($booking->departure_date)) {
            throw new InvalidArgumentException('The new departure date must be after '.$booking->departure_date->format('j F Y').'.');
        }

        $extra = new StayDates($booking->departure_date, $newDeparture);

        try {
            return DB::transaction(function () use ($booking, $newDeparture, $extra, $by): Invoice {
                $lines = [];

                foreach ($booking->activeBookingRooms()->with(['accommodationType', 'room'])->get() as $bookingRoom) {
                    /** @var BookingRoom $bookingRoom */
                    $this->ledger->claimForBookingRoom($bookingRoom, $extra);

                    $quote = $this->rates->quote($bookingRoom->accommodationType, $extra, $bookingRoom->adults, $bookingRoom->children);

                    $bookingRoom->forceFill([
                        'departure_date' => $newDeparture->toDateString(),
                        'accommodation_total_cents' => $bookingRoom->accommodation_total_cents->add($quote->total()),
                    ])->save();

                    $lines[] = [
                        'description' => sprintf(
                            'Extension: %s, %s – %s (%d %s)',
                            $bookingRoom->room->name,
                            $extra->arrival->format('j M Y'),
                            $extra->departure->format('j M Y'),
                            $extra->nights(),
                            Str::plural('night', $extra->nights()),
                        ),
                        'quantity' => 1,
                        'unit' => $quote->total(),
                        'total' => $quote->total(),
                    ];
                }

                $invoice = $this->documents->invoiceForCharges($booking, $lines, $by);

                $booking->forceFill([
                    'departure_date' => $newDeparture->toDateString(),
                    'total_cents' => $booking->total_cents->add($invoice->total_cents),
                ])->save();

                activity('bookings')
                    ->performedOn($booking)
                    ->causedBy($by)
                    ->event('stay_extended')
                    ->withProperties(['nights' => $extra->nights(), 'invoice' => $invoice->number])
                    ->log('Stay extended');

                $this->payments->sync($booking);

                return $invoice;
            });
        } catch (QueryException $exception) {
            if (RoomNightLedger::isConflict($exception)) {
                throw new RoomUnavailableException('The room is booked on some of those nights. Move the guest to another room first, or choose an earlier date.');
            }

            throw $exception;
        }
    }
}
