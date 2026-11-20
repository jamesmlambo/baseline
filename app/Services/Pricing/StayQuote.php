<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Support\Money;

/**
 * Price breakdown for one room for one stay (OBK-01).
 */
final readonly class StayQuote
{
    /**
     * @param  array<string, Money>  $nightlyRates  keyed by Y-m-d
     */
    public function __construct(
        public array $nightlyRates,
        public Money $extraGuestPerNight,
        public int $extraGuests,
    ) {}

    public function accommodationSubtotal(): Money
    {
        return array_reduce($this->nightlyRates, fn (Money $carry, Money $rate): Money => $carry->add($rate), Money::zero());
    }

    public function extraGuestTotal(): Money
    {
        return $this->extraGuestPerNight->multiply($this->extraGuests * count($this->nightlyRates));
    }

    public function total(): Money
    {
        return $this->accommodationSubtotal()->add($this->extraGuestTotal());
    }
}
