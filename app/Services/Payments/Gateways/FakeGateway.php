<?php

declare(strict_types=1);

namespace App\Services\Payments\Gateways;

use App\Models\Payment;
use App\Services\Payments\CheckoutSession;
use App\Services\Payments\Contracts\PaymentGateway;
use App\Services\Payments\Exceptions\InvalidWebhookSignature;
use App\Services\Payments\GatewayEvent;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Development-only stand-in for a real payment provider. It sends the guest to a local
 * "approve / decline" page and delivers HMAC-signed webhooks, so the whole flow — including
 * signature checks, duplicate and out-of-order events — can be tested without real money.
 */
class FakeGateway implements PaymentGateway
{
    public const SIGNATURE_HEADER = 'X-Fake-Signature';

    public function __construct()
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The fake payment gateway must never run in production.');
        }
    }

    public function name(): string
    {
        return 'fake';
    }

    public function createCheckout(Payment $payment, string $returnUrl, string $cancelUrl): CheckoutSession
    {
        $reference = 'fake_'.Str::lower(Str::random(24));

        return new CheckoutSession(
            redirectUrl: URL::temporarySignedRoute('dev.fake-gateway.show', now()->addHour(), [
                'reference' => $reference,
                'return' => $returnUrl,
                'cancel' => $cancelUrl,
            ]),
            gatewayReference: $reference,
        );
    }

    public function parseWebhook(Request $request): GatewayEvent
    {
        $body = $request->getContent();
        $signature = (string) $request->header(self::SIGNATURE_HEADER);

        if ($signature === '' || ! hash_equals($this->sign($body), $signature)) {
            throw new InvalidWebhookSignature('Fake gateway signature mismatch.');
        }

        /** @var array{id: string, type: string, reference: string, amount: int, occurred_at: string} $payload */
        $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

        return new GatewayEvent(
            eventId: $payload['id'],
            type: $payload['type'],
            gatewayReference: $payload['reference'],
            amountCents: (int) $payload['amount'],
            occurredAt: CarbonImmutable::parse($payload['occurred_at']),
            payload: $payload,
        );
    }

    public function refund(Payment $payment, Money $amount): string
    {
        return 'fake_refund_'.Str::lower(Str::random(16));
    }

    /**
     * Build a signed webhook body, as the provider would send it.
     *
     * @return array{body: string, signature: string}
     */
    public function makeWebhook(string $type, string $reference, int $amountCents, ?string $eventId = null, ?CarbonImmutable $occurredAt = null): array
    {
        $body = json_encode([
            'id' => $eventId ?? 'evt_'.Str::lower(Str::random(20)),
            'type' => $type,
            'reference' => $reference,
            'amount' => $amountCents,
            'occurred_at' => ($occurredAt ?? now()->toImmutable())->toIso8601String(),
        ], JSON_THROW_ON_ERROR);

        return ['body' => $body, 'signature' => $this->sign($body)];
    }

    private function sign(string $body): string
    {
        $secret = (string) (config('payments.gateways.fake.secret') ?: config('app.key'));

        return hash_hmac('sha256', $body, $secret);
    }
}
