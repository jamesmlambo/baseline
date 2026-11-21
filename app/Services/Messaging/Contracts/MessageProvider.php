<?php

declare(strict_types=1);

namespace App\Services\Messaging\Contracts;

use App\Enums\MessageChannel;
use App\Services\Messaging\ProviderReceipt;

/**
 * An SMS or WhatsApp provider (MSG-01). Real adapters are added only once the client chooses a
 * provider and its templates are approved (PEND-07).
 */
interface MessageProvider
{
    public function name(): string;

    public function channel(): MessageChannel;

    /**
     * @throws \RuntimeException when the provider rejects or cannot be reached (the job retries)
     */
    public function send(string $to, string $body, ?string $providerTemplateId = null): ProviderReceipt;
}
