<?php

declare(strict_types=1);

namespace App\Services\Channels;

use App\Models\Channel;
use InvalidArgumentException;

/**
 * Picks the driver for a channel. Real drivers are registered here once a provider is approved.
 */
class ChannelDrivers
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return ['null' => 'Not connected'];
    }

    public function for(Channel $channel): ChannelManager
    {
        return match ($channel->driver) {
            'null' => app(NullChannelManager::class),
            default => throw new InvalidArgumentException("Channel driver [{$channel->driver}] is not available."),
        };
    }
}
