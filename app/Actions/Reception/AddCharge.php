<?php

declare(strict_types=1);

namespace App\Actions\Reception;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\DocumentIssuer;
use App\Services\Payments\PaymentLedger;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Adds a charge to a stay (REC-03) on its own invoice. A full running folio with charge-to-room
 * from the guest portal arrives in Milestones 7 and 10.
 */
class AddCharge
{
    public function __construct(
        private readonly DocumentIssuer $documents,
        private readonly PaymentLedger $payments,
    ) {}

    public function handle(Booking $booking, string $description, int $quantity, Money $unitPrice, User $by): Invoice
    {
        if (! $by->can('update', $booking)) {
            throw new AuthorizationException('You cannot add charges to this booking.');
        }

        if (! in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::CheckedIn], true)) {
            throw new InvalidArgumentException('Charges can only be added to confirmed or checked-in bookings.');
        }

        if ($quantity < 1 || $unitPrice->cents <= 0 || trim($description) === '') {
            throw new InvalidArgumentException('Enter a description, a quantity of at least 1 and a price.');
        }

        $total = $unitPrice->multiply($quantity);

        $invoice = $this->documents->invoiceForCharges($booking, [[
            'description' => trim($description),
            'quantity' => $quantity,
            'unit' => $unitPrice,
            'total' => $total,
        ]], $by);

        $booking->forceFill(['total_cents' => $booking->total_cents->add($invoice->total_cents)])->save();
        $this->payments->sync($booking);

        return $invoice;
    }
}
