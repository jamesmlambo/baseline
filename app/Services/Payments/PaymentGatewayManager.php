<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Services\Payments\Contracts\PaymentGateway;
use App\Services\Payments\Gateways\FakeGateway;
use InvalidArgumentException;

/**
 * Resolves the configured gateway by name. Real providers are registered here once approved.
 */
class PaymentGatewayManager
{
    public function default(): PaymentGateway
    {
        return $this->driver((string) config('payments.gateway', 'fake'));
    }

    public function driver(string $name): PaymentGateway
    {
        return match ($name) {
            'fake' => app(FakeGateway::class),
            default => throw new InvalidArgumentException("Payment gateway [{$name}] is not configured."),
        };
    }
}
