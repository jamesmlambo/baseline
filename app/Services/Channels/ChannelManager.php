<?php

declare(strict_types=1);

namespace App\Services\Channels;

use App\Models\AccommodationType;
use App\Models\Channel;
use Carbon\CarbonImmutable;

/**
 * CHN-01: a channel manager or OTA connection. Only the null driver exists until the client
 * chooses a provider and architecture (PEND-07, PEND-08). Every implementation must be idempotent:
 * the same reservation or push sent twice changes nothing.
 */
interface ChannelManager
{
    /**
     * Whether this driver really talks to a provider. The null driver returns false, and the
     * app never claims a sync happened through it (CHN-04).
     */
    public function isConnected(): bool;

    /**
     * New, changed and cancelled reservations since the given time.
     *
     * @return iterable<int, ChannelReservationData>
     *
     * @throws \RuntimeException when the provider cannot be reached
     */
    public function fetchReservations(Channel $channel, CarbonImmutable $since): iterable;

    /**
     * Rooms free per night for a room type.
     *
     * @param  array<string, int>  $freeRoomsByDate  Y-m-d => rooms free
     */
    public function pushAvailability(Channel $channel, AccommodationType $type, array $freeRoomsByDate): void;

    /**
     * Nightly rate per night for a room type, in cents.
     *
     * @param  array<string, int>  $rateCentsByDate  Y-m-d => cents
     */
    public function pushRates(Channel $channel, AccommodationType $type, array $rateCentsByDate): void;
}
