<?php

declare(strict_types=1);

namespace App\Services\Payments;

use Carbon\CarbonImmutable;

/**
 * A provider webhook translated into our terms.
 *
 * @phpstan-type EventType 'payment.succeeded'|'payment.failed'|'payment.cancelled'
 */
final readonly class GatewayEvent
{
    public const SUCCEEDED = 'payment.succeeded';

    public const FAILED = 'payment.failed';

    public const CANCELLED = 'payment.cancelled';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $eventId,
        public string $type,
        public string $gatewayReference,
        public int $amountCents,
        public CarbonImmutable $occurredAt,
        public array $payload,
    ) {}
}
