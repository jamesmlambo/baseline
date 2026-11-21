<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\MessageChannel;
use App\Services\Messaging\Contracts\MessageProvider;
use App\Services\Messaging\Providers\LogProvider;
use InvalidArgumentException;

/**
 * Resolves the configured provider per channel. Real providers are registered here once approved.
 */
class MessageProviderManager
{
    public function for(MessageChannel $channel): MessageProvider
    {
        $driver = (string) config('messaging.'.$channel->value, 'log');

        return match ($driver) {
            'log' => new LogProvider($channel),
            default => throw new InvalidArgumentException("{$channel->label()} provider [{$driver}] is not configured."),
        };
    }
}
