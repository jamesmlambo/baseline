<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Booking;
use App\Models\BookingExtra;
use App\Models\BookingRoom;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\Receipt;
use App\Models\Refund;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Issues quotations, invoices, receipts and credit notes (PAY-01..04). Every document is
 * numbered and written in one transaction and is immutable afterwards.
 */
class DocumentIssuer
{
    public function __construct(
        private readonly DocumentNumberer $numberer,
        private readonly FinanceSettings $settings,
    ) {}

    public function quotationForBooking(Booking $booking, ?User $by = null, int $validDays = 7): Quotation
    {
        return DB::transaction(function () use ($booking, $by, $validDays): Quotation {
            $lines = $this->bookingLines($booking);
            [$subtotal, $tax, $total] = $this->totals($lines);

            $quotation = Quotation::query()->create([
                'number' => $this->numberer->next('quotation'),
                'booking_id' => $booking->id,
                'guest_id' => $booking->guest_id,
                'recipient_name' => $booking->lead_guest_name,
                'subtotal_cents' => $subtotal,
                'tax_cents' => $tax,
                'total_cents' => $total,
                'valid_until' => today()->addDays($validDays)->toDateString(),
                'issued_by' => $by?->id,
                'issued_at' => now(),
            ]);

            $this->writeLines($quotation, $lines);

            return $quotation;
        });
    }

    public function invoiceForBooking(Booking $booking, ?User $by = null): Invoice
    {
        return DB::transaction(function () use ($booking, $by): Invoice {
            $lines = $this->bookingLines($booking);

            return $this->createInvoice($booking, $lines, $by);
        });
    }

    /**
     * PAY-04: turn a quotation into an invoice with the same lines, no retyping.
     */
    public function convertQuotation(Quotation $quotation, ?User $by = null): Invoice
    {
        if ($quotation->converted_invoice_id !== null) {
            throw new InvalidArgumentException("Quotation {$quotation->number} has already been converted.");
        }

        return DB::transaction(function () use ($quotation, $by): Invoice {
            $lines = $quotation->lines->map(fn ($line): array => [
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit' => $line->unit_price_cents,
                'total' => $line->total_cents,
            ])->all();

            $invoice = $this->createInvoice($quotation->booking, $lines, $by, $quotation);
            $quotation->forceFill(['converted_invoice_id' => $invoice->id])->save();

            return $invoice;
        });
    }

    public function receiptForPayment(Payment $payment): Receipt
    {
        $existing = $payment->receipt()->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(fn (): Receipt => Receipt::query()->create([
            'number' => $this->numberer->next('receipt'),
            'payment_id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'booking_id' => $payment->booking_id,
            'recipient_name' => $payment->booking?->lead_guest_name ?? $payment->invoice?->recipient_name ?? 'Guest',
            'amount_cents' => $payment->amount_cents,
            'public_token' => Str::random(48),
            'issued_at' => now(),
        ]));
    }

    public function creditNote(Invoice $invoice, Money $amount, string $reason, ?User $by = null, ?Refund $refund = null): CreditNote
    {
        if ($amount->cents <= 0) {
            throw new InvalidArgumentException('A credit note must be for a positive amount.');
        }

        return DB::transaction(fn (): CreditNote => CreditNote::query()->create([
            'number' => $this->numberer->next('credit_note'),
            'invoice_id' => $invoice->id,
            'refund_id' => $refund?->id,
            'booking_id' => $invoice->booking_id,
            'recipient_name' => $invoice->recipient_name,
            'reason' => $reason,
            'total_cents' => $amount,
            'issued_by' => $by?->id,
            'issued_at' => now(),
        ]));
    }

    /**
     * @param  list<array{description: string, quantity: float|int, unit: Money, total: Money}>  $lines
     */
    private function createInvoice(?Booking $booking, array $lines, ?User $by, ?Quotation $quotation = null): Invoice
    {
        [$subtotal, $tax, $total] = $this->totals($lines);

        $invoice = Invoice::query()->create([
            'number' => $this->numberer->next('invoice'),
            'booking_id' => $booking?->id,
            'guest_id' => $booking?->guest_id ?? $quotation?->guest_id,
            'quotation_id' => $quotation?->id,
            'recipient_name' => $booking?->lead_guest_name ?? $quotation?->recipient_name ?? 'Guest',
            'subtotal_cents' => $subtotal,
            'tax_cents' => $tax,
            'total_cents' => $total,
            'due_on' => today()->addDays($this->settings->invoiceDueDays())->toDateString(),
            'public_token' => Str::random(48),
            'issued_by' => $by?->id,
            'issued_at' => now(),
        ]);

        $this->writeLines($invoice, $lines);

        return $invoice;
    }

    /**
     * @return list<array{description: string, quantity: float|int, unit: Money, total: Money}>
     */
    private function bookingLines(Booking $booking): array
    {
        $booking->loadMissing(['activeBookingRooms.accommodationType', 'extras']);
        $nights = $booking->nights();
        $lines = [];

        foreach ($booking->activeBookingRooms as $bookingRoom) {
            /** @var BookingRoom $bookingRoom */
            $lines[] = [
                'description' => sprintf(
                    '%s, %s – %s (%d %s, %d %s)',
                    $bookingRoom->accommodationType->name,
                    $bookingRoom->arrival_date->format('j M Y'),
                    $bookingRoom->departure_date->format('j M Y'),
                    $nights,
                    Str::plural('night', $nights),
                    $bookingRoom->adults + $bookingRoom->children,
                    Str::plural('guest', $bookingRoom->adults + $bookingRoom->children),
                ),
                'quantity' => 1,
                'unit' => $bookingRoom->accommodation_total_cents,
                'total' => $bookingRoom->accommodation_total_cents,
            ];
        }

        foreach ($booking->extras as $extra) {
            /** @var BookingExtra $extra */
            $lines[] = [
                'description' => $extra->description,
                'quantity' => $extra->quantity,
                'unit' => $extra->unit_price_cents,
                'total' => $extra->total_cents,
            ];
        }

        return $lines;
    }

    /**
     * VAT is only added when a rate is configured (VERIFY with a tax adviser).
     *
     * @param  list<array{total: Money}>  $lines
     * @return array{0: Money, 1: Money, 2: Money}
     */
    private function totals(array $lines): array
    {
        $subtotal = array_reduce($lines, fn (Money $carry, array $line): Money => $carry->add($line['total']), Money::zero());
        $rate = $this->settings->vatRatePercent();
        $tax = $rate !== null ? Money::cents(intdiv($subtotal->cents * $rate + 50, 100)) : Money::zero();

        return [$subtotal, $tax, $subtotal->add($tax)];
    }

    /**
     * @param  list<array{description: string, quantity: float|int, unit: Money, total: Money}>  $lines
     */
    private function writeLines(Invoice|Quotation $document, array $lines): void
    {
        foreach (array_values($lines) as $index => $line) {
            $document->lines()->create([
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price_cents' => $line['unit'],
                'total_cents' => $line['total'],
                'sort_order' => $index,
            ]);
        }
    }
}
