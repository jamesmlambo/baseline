<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PaymentRecordStatus;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\Contracts\PaymentGateway;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Handles provider webhooks safely (PAY-06): signature first, then each event exactly once,
 * and never lets a late failure undo a success.
 */
class WebhookProcessor
{
    public function __construct(private readonly PaymentLedger $ledger) {}

    /**
     * @return string Outcome, for logging and tests
     */
    public function process(PaymentGateway $gateway, Request $request): string
    {
        $event = $gateway->parseWebhook($request);

        try {
            // Own savepoint: a duplicate insert must not poison an outer transaction.
            $record = DB::transaction(fn (): PaymentWebhookEvent => PaymentWebhookEvent::query()->create([
                'gateway' => $gateway->name(),
                'event_id' => $event->eventId,
                'event_type' => $event->type,
                'payload' => $event->payload,
                'occurred_at' => $event->occurredAt,
            ]));
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23505') {
                return 'duplicate';
            }

            throw $exception;
        }

        $outcome = DB::transaction(function () use ($gateway, $event, $record): string {
            $payment = Payment::query()
                ->where('gateway', $gateway->name())
                ->where('gateway_reference', $event->gatewayReference)
                ->first();

            if ($payment === null) {
                return 'unknown_payment';
            }

            $record->forceFill(['payment_id' => $payment->id])->save();

            return match ($event->type) {
                GatewayEvent::SUCCEEDED => $this->succeeded($payment, $event),
                GatewayEvent::FAILED => $this->ledger->markFailed($payment, PaymentRecordStatus::Failed, 'Declined by payment provider') ? 'failed' : 'ignored_after_success',
                GatewayEvent::CANCELLED => $this->ledger->markFailed($payment, PaymentRecordStatus::Cancelled, 'Cancelled by guest') ? 'cancelled' : 'ignored_after_success',
                default => 'ignored_type',
            };
        });

        $record->forceFill(['processed_at' => now(), 'outcome' => $outcome])->save();

        return $outcome;
    }

    private function succeeded(Payment $payment, GatewayEvent $event): string
    {
        if ($event->amountCents !== $payment->amount_cents->cents) {
            $payment->forceFill([
                'status' => PaymentRecordStatus::ReconciliationRequired,
                'exception_reason' => sprintf('Provider reported %d cents, expected %d cents.', $event->amountCents, $payment->amount_cents->cents),
            ])->save();

            return 'amount_mismatch';
        }

        $this->ledger->markSucceeded($payment);

        return 'succeeded';
    }
}
