<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentLedger;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * An authorised person confirms an EFT has reached the bank account (PAY-07).
 * Proof-of-payment documents alone never get this far.
 */
class VerifyEftPayment
{
    public function __construct(private readonly PaymentLedger $ledger) {}

    public function handle(Payment $payment, User $by, string $bankReference, ?Money $amountSeen = null): Payment
    {
        if (! $by->can('verify', $payment)) {
            throw new AuthorizationException('Only the owner, a manager or an administrator can verify EFT payments.');
        }

        if ($payment->method !== PaymentMethod::Eft || $payment->status !== PaymentRecordStatus::PendingVerification) {
            throw new InvalidArgumentException('Only EFT payments awaiting verification can be verified.');
        }

        if (trim($bankReference) === '') {
            throw new InvalidArgumentException('Enter the bank statement reference you checked.');
        }

        if ($amountSeen !== null && ! $amountSeen->equals($payment->amount_cents)) {
            throw new InvalidArgumentException('The amount on the bank statement does not match. Record the correct amount as a new payment instead.');
        }

        return $this->ledger->markSucceeded($payment, $by, $bankReference);
    }

    public function reject(Payment $payment, User $by, string $reason): void
    {
        if (! $by->can('verify', $payment)) {
            throw new AuthorizationException('Only the owner, a manager or an administrator can reject EFT payments.');
        }

        $this->ledger->markFailed($payment, PaymentRecordStatus::Failed, 'Not found on bank statement: '.$reason);
    }
}
