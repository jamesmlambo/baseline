<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Reception\AddCharge;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\DocumentIssuer;
use App\Services\Payments\PaymentLedger;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Issues an invoice against a booking from the Invoices section (PAY-02).
 *
 * "Stay": the whole stay, only when the booking has no invoice yet (bookings normally get
 * one when they are made), so the guest is never billed twice. "Charge": an extra charge
 * on its own invoice, added to what the guest owes. Either way the invoice shows UNPAID
 * until the money is received.
 */
class IssueBookingInvoice
{
    public const STAY = 'stay';

    public const CHARGE = 'charge';

    public function __construct(
        private readonly DocumentIssuer $documents,
        private readonly PaymentLedger $payments,
        private readonly AddCharge $addCharge,
    ) {}

    public function handle(Booking $booking, string $kind, User $by, ?string $description = null, int $quantity = 1, ?Money $unitPrice = null): Invoice
    {
        if (! $by->can('create', Invoice::class)) {
            throw new AuthorizationException('You cannot issue invoices.');
        }

        return match ($kind) {
            self::STAY => $this->stayInvoice($booking, $by),
            self::CHARGE => $this->addCharge->handle($booking, (string) $description, $quantity, $unitPrice ?? Money::zero(), $by),
            default => throw new InvalidArgumentException('Choose what to invoice.'),
        };
    }

    private function stayInvoice(Booking $booking, User $by): Invoice
    {
        return DB::transaction(function () use ($booking, $by): Invoice {
            /** @var Booking $locked */
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [BookingStatus::Cancelled, BookingStatus::Expired], true)) {
                throw new InvalidArgumentException("Booking {$locked->reference} is {$locked->status->label()}, so it cannot be invoiced.");
            }

            $existing = $locked->currentInvoice();

            if ($existing !== null) {
                throw new InvalidArgumentException("Booking {$locked->reference} already has invoice {$existing->number}. Send that one, or invoice an extra charge.");
            }

            $invoice = $this->documents->invoiceForBooking($locked, $by);
            $this->payments->sync($locked);

            return $invoice;
        });
    }
}
