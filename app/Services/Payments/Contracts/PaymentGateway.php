<?php

declare(strict_types=1);

namespace App\Services\Payments\Contracts;

use App\Models\Payment;
use App\Services\Payments\CheckoutSession;
use App\Services\Payments\Exceptions\InvalidWebhookSignature;
use App\Services\Payments\GatewayEvent;
use App\Support\Money;
use Illuminate\Http\Request;

/**
 * An online payment provider (PAY-05). Adapters for Ozow, PayFast or Yoco are added only once the
 * client selects one and it has been tested in the provider's sandbox (PEND-07).
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Start a hosted checkout. Card details are entered only on the provider's page (PAY-09).
     */
    public function createCheckout(Payment $payment, string $returnUrl, string $cancelUrl): CheckoutSession;

    /**
     * Verify the webhook signature and translate the provider's payload.
     *
     * @throws InvalidWebhookSignature
     */
    public function parseWebhook(Request $request): GatewayEvent;

    /**
     * Ask the provider to refund money. Returns the provider's refund reference.
     */
    public function refund(Payment $payment, Money $amount): string;
}
