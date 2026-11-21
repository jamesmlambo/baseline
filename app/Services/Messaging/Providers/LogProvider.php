<?php

declare(strict_types=1);

namespace App\Services\Messaging\Providers;

use App\Enums\MessageChannel;
use App\Services\Messaging\Contracts\MessageProvider;
use App\Services\Messaging\ProviderReceipt;
use App\Support\Money;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes messages to the application log instead of sending them (MSG-01). Costs nothing.
 */
class LogProvider implements MessageProvider
{
    public function __construct(private readonly MessageChannel $channel) {}

    public function name(): string
    {
        return 'log';
    }

    public function channel(): MessageChannel
    {
        return $this->channel;
    }

    public function send(string $to, string $body, ?string $providerTemplateId = null): ProviderReceipt
    {
        Log::info("[{$this->channel->label()} NOT SENT — log driver] to {$to}: {$body}");

        return new ProviderReceipt('log_'.Str::lower(Str::random(16)), Money::zero(), delivered: true);
    }
}
