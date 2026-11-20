<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\Bookings\BookingStateMachine;
use App\Services\Finance\DocumentIssuer;
use App\Services\Finance\FinanceSettings;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * All payment status changes (PAY-01, PAY-06, PAY-07) and their effect on the booking.
 *
 * Rules:
 * - Only Succeeded is money received. Proof of EFT stays PendingVerification.
 * - A Succeeded payment is never downgraded by a later (out-of-order) failure event.
 * - When verified payments reach the deposit, an open booking is confirmed.
 * - Money arriving for an expired or cancelled booking is flagged for staff (PEND-02).
 */
class PaymentLedger
{
    public function __construct(
        private readonly DocumentIssuer $documents,
        private readonly FinanceSettings $settings,
        private readonly BookingStateMachine $bookings,
    ) {}

    public function startOnlinePayment(Booking $booking, PaymentMethod $method, Money $amount, string $gateway, string $gatewayReference): Payment
    {
        if (! $method->isOnline()) {
            throw new InvalidArgumentException("{$method->label()} is not an online payment method.");
        }

        return Payment::query()->create([
            'booking_id' => $booking->id,
            'invoice_id' => $booking->currentInvoice()?->id,
            'method' => $method,
            'status' => PaymentRecordStatus::Initiated,
            'amount_cents' => $amount,
            'gateway' => $gateway,
            'gateway_reference' => $gatewayReference,
            'payer_reference' => $booking->reference,
        ]);
    }

    /**
     * A guest says they have paid by EFT (optionally with proof). Not money received yet.
     */
    public function recordEftNotice(Booking $booking, Money $amount, ?string $notes = null): Payment
    {
        $payment = Payment::query()->create([
            'booking_id' => $booking->id,
            'invoice_id' => $booking->currentInvoice()?->id,
            'method' => PaymentMethod::Eft,
            'status' => PaymentRecordStatus::PendingVerification,
            'amount_cents' => $amount,
            'payer_reference' => $booking->reference,
            'notes' => $notes,
        ]);

        $this->sync($booking);

        return $payment;
    }

    /**
     * Staff record a payment taken at reception. Cash in hand is received immediately;
     * an EFT still needs verification against the bank (REC-03, PAY-07).
     */
    public function recordAtReception(Booking $booking, PaymentMethod $method, Money $amount, User $by, ?string $bankReference = null, ?string $notes = null): Payment
    {
        if ($method->isOnline()) {
            throw new InvalidArgumentException('Online payments are recorded by the payment provider, not by hand.');
        }

        return DB::transaction(function () use ($booking, $method, $amount, $by, $bankReference, $notes): Payment {
            $payment = Payment::query()->create([
                'booking_id' => $booking->id,
                'invoice_id' => $booking->currentInvoice()?->id,
                'method' => $method,
                'status' => PaymentRecordStatus::PendingVerification,
                'amount_cents' => $amount,
                'bank_reference' => $bankReference,
                'payer_reference' => $booking->reference,
                'notes' => $notes,
                'recorded_by' => $by->id,
            ]);

            if ($method === PaymentMethod::Cash) {
                $this->markSucceeded($payment, $by);
            } else {
                $this->sync($booking);
            }

            return $payment->refresh();
        });
    }

    /**
     * Money confirmed: by the gateway (verified webhook) or by authorised staff checking the bank.
     */
    public function markSucceeded(Payment $payment, ?User $verifiedBy = null, ?string $bankReference = null): Payment
    {
        return DB::transaction(function () use ($payment, $verifiedBy, $bankReference): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentRecordStatus::Succeeded) {
                return $locked;
            }

            if (in_array($locked->status, [PaymentRecordStatus::Failed, PaymentRecordStatus::Cancelled], true) && $verifiedBy === null) {
                // A gateway success after a failure: real money has arrived, so record it and flag it.
                $locked->exception_reason = 'Payment succeeded after it was reported as failed. Please check.';
            }

            $locked->forceFill([
                'status' => PaymentRecordStatus::Succeeded,
                'verified_by' => $verifiedBy?->id,
                'verified_at' => now(),
                'received_at' => $locked->received_at ?? now(),
                'bank_reference' => $bankReference ?? $locked->bank_reference,
            ])->save();

            $this->documents->receiptForPayment($locked);

            if ($locked->booking !== null) {
                $this->sync($locked->booking);
            }

            return $locked;
        });
    }

    /**
     * A failure or cancellation never overrides money already received (out-of-order events).
     */
    public function markFailed(Payment $payment, PaymentRecordStatus $status = PaymentRecordStatus::Failed, ?string $reason = null): bool
    {
        if (! in_array($status, [PaymentRecordStatus::Failed, PaymentRecordStatus::Cancelled], true)) {
            throw new InvalidArgumentException('Use markSucceeded() for successful payments.');
        }

        return DB::transaction(function () use ($payment, $status, $reason): bool {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentRecordStatus::Succeeded) {
                return false;
            }

            $locked->forceFill(['status' => $status, 'notes' => $reason ?? $locked->notes])->save();

            if ($locked->booking !== null) {
                $this->sync($locked->booking);
            }

            return true;
        });
    }

    public function flagForReconciliation(Payment $payment, string $reason): void
    {
        $payment->forceFill(['exception_reason' => $reason])->save();
    }

    public function resolveException(Payment $payment): void
    {
        $payment->forceFill(['exception_reason' => null])->save();
    }

    /**
     * Recalculate the booking's payment status, confirm it if enough has been paid,
     * and flag money that arrived for a booking that no longer holds a room.
     */
    public function sync(Booking $booking): void
    {
        $booking->refresh();
        $invoice = $booking->currentInvoice();
        $payments = $booking->payments()->get();

        $received = $payments->where('status', PaymentRecordStatus::Succeeded)->sum(fn (Payment $payment): int => $payment->amount_cents->cents);
        $refunded = $payments->sum(fn (Payment $payment): int => $payment->refunded_cents->cents);
        $net = $received - $refunded;
        $awaitingVerification = $payments->contains(fn (Payment $payment): bool => $payment->status === PaymentRecordStatus::PendingVerification);
        $owed = $invoice !== null ? $invoice->total_cents->subtract($invoice->creditedAmount())->cents : $booking->total_cents->cents;

        $status = match (true) {
            $refunded > 0 && $net <= 0 => PaymentStatus::Refunded,
            $refunded > 0 => PaymentStatus::PartRefunded,
            $received > 0 && $net >= $owed => PaymentStatus::VerifiedPaid,
            $awaitingVerification => PaymentStatus::PendingVerification,
            $received > 0 => PaymentStatus::PartPaid,
            default => PaymentStatus::Unpaid,
        };

        if ($booking->payment_status !== $status) {
            $booking->forceFill(['payment_status' => $status])->save();
        }

        if ($received === 0) {
            return;
        }

        if (in_array($booking->status, [BookingStatus::Quote, BookingStatus::Hold, BookingStatus::Pending], true)
            && $net >= $booking->deposit_due_cents->cents) {
            $this->bookings->transition($booking, BookingStatus::Confirmed, 'Payment received');

            return;
        }

        if (in_array($booking->status, [BookingStatus::Expired, BookingStatus::Cancelled], true)) {
            $payments
                ->where('status', PaymentRecordStatus::Succeeded)
                ->whereNull('exception_reason')
                ->filter(fn (Payment $payment): bool => $payment->refunded_cents->cents < $payment->amount_cents->cents)
                ->each(fn (Payment $payment) => $this->flagForReconciliation(
                    $payment,
                    "Payment received but booking is {$booking->status->label()}. Reinstate the booking if the room is still free, otherwise refund (PEND-02).",
                ));
        }
    }
}
