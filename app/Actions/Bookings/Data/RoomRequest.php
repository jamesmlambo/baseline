<?php

declare(strict_types=1);

namespace App\Actions\Bookings\Data;

use App\Models\AccommodationType;
use App\Models\Room;

/**
 * One room within a booking request. Guests choose a type; staff may name a specific room.
 */
final readonly class RoomRequest
{
    public function __construct(
        public AccommodationType $type,
        public int $adults,
        public int $children = 0,
        public ?Room $preferredRoom = null,
    ) {}
}
