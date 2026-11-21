<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Support\Money;

final readonly class ProviderReceipt
{
    public function __construct(
        public string $providerMessageId,
        public Money $cost,
        public bool $delivered = false,
    ) {}
}
