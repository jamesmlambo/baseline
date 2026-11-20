<?php

declare(strict_types=1);

namespace App\Services\Availability;

use App\Models\AccommodationType;
use App\Services\Pricing\StayQuote;

/**
 * One accommodation type's availability and price for a searched stay.
 */
final readonly class AvailabilityResult
{
    public function __construct(
        public AccommodationType $type,
        public int $availableRooms,
        public StayQuote $quote,
    ) {}

    public function isAvailable(): bool
    {
        return $this->availableRooms > 0;
    }
}
