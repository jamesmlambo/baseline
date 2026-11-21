<?php

declare(strict_types=1);

namespace App\Services\Channels;

use App\Models\AccommodationType;
use App\Models\Channel;
use Carbon\CarbonImmutable;

/**
 * Not connected to anything. Fetches nothing and pushes nothing; sync runs through it are
 * recorded as "not connected", never as successful.
 */
class NullChannelManager implements ChannelManager
{
    public function isConnected(): bool
    {
        return false;
    }

    public function fetchReservations(Channel $channel, CarbonImmutable $since): iterable
    {
        return [];
    }

    public function pushAvailability(Channel $channel, AccommodationType $type, array $freeRoomsByDate): void {}

    public function pushRates(Channel $channel, AccommodationType $type, array $rateCentsByDate): void {}
}
