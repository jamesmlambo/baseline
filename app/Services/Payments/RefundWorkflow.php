<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PaymentRecordStatus;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Finance\DocumentIssuer;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Refunds need two different authorised people (DEC-06, PAY-08): one requests, another approves.
 * Approval sends the refund to the gateway (online payments) or records an EFT refund to be paid,
 * issues a credit note and updates the booking.
 */
class RefundWorkflow
{
    public function __construct(
        private readonly PaymentLedger $ledger,
        private readonly DocumentIssuer $documents,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    public function request(Payment $payment, Money $amount, string $reason, User $by): Refund
    {
        $this->authorize($by, 'requestRefund', $payment);

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required for every refund.');
        }

        if ($payment->status !== PaymentRecordStatus::Succeeded) {
            throw new InvalidArgumentException('Only received payments can be refunded.');
        }

        $pending = (int) $payment->refunds()->where('status', RefundStatus::Requested)->sum('amount_cents');

        if ($amount->cents <= 0 || $amount->cents + $pending > $payment->refundableAmount()->cents) {
            throw new InvalidArgumentException('The refund is more than what is left on this payment.');
        }

        return Refund::query()->create([
            'payment_id' => $payment->id,
            'amount_cents' => $amount,
            'reason' => $reason,
            'status' => RefundStatus::Requested,
            'requested_by' => $by->id,
        ]);
    }

    public function approve(Refund $refund, User $by): Refund
    {
        $this->authorize($by, 'approveRefund', $refund->payment);

        if ($refund->requested_by === $by->id) {
            throw new AuthorizationException('A refund must be approved by someone other than the person who requested it.');
        }

        return DB::transaction(function () use ($refund, $by): Refund {
            /** @var Refund $locked */
            $locked = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== RefundStatus::Requested) {
                throw new InvalidArgumentException('This refund has already been decided.');
            }

            /** @var Payment $payment */
            $payment = Payment::query()->whereKey($locked->payment_id)->lockForUpdate()->firstOrFail();

            if ($locked->amount_cents->cents > $payment->refundableAmount()->cents) {
                throw new InvalidArgumentException('The refund is more than what is left on this payment.');
            }

            $reference = $payment->method->isOnline() && $payment->gateway !== null
                ? $this->gateways->driver($payment->gateway)->refund($payment, $locked->amount_cents)
                : null;

            $locked->forceFill([
                'status' => RefundStatus::Processed,
                'approved_by' => $by->id,
                'approved_at' => now(),
                'processed_at' => now(),
                'gateway_refund_reference' => $reference,
            ])->save();

            $payment->forceFill(['refunded_cents' => $payment->refunded_cents->add($locked->amount_cents)])->save();

            if ($payment->invoice !== null) {
                $this->documents->creditNote($payment->invoice, $locked->amount_cents, 'Refund: '.$locked->reason, $by, $locked);
            }

            if ($payment->booking !== null) {
                $this->ledger->sync($payment->booking);
            }

            return $locked;
        });
    }

    public function reject(Refund $refund, User $by, string $reason): Refund
    {
        $this->authorize($by, 'approveRefund', $refund->payment);

        if ($refund->status !== RefundStatus::Requested) {
            throw new InvalidArgumentException('This refund has already been decided.');
        }

        $refund->forceFill([
            'status' => RefundStatus::Rejected,
            'rejected_by' => $by->id,
            'rejection_reason' => $reason,
        ])->save();

        return $refund;
    }

    private function authorize(User $user, string $ability, Payment $payment): void
    {
        if (! $user->can($ability, $payment)) {
            throw new AuthorizationException('You are not allowed to do that.');
        }
    }
}
