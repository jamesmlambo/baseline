<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\AccommodationType;
use App\Models\SeasonalRate;
use App\Support\Money;
use App\Support\StayDates;
use InvalidArgumentException;

/**
 * Prices a stay from the rate engine (WEB-06): seasonal rate for each night where one applies,
 * otherwise the type's base rate, plus extra-guest charges above base occupancy.
 * Taxes and fees come with the finance module (M4).
 */
class RateCalculator
{
    public function quote(AccommodationType $type, StayDates $stay, int $adults, int $children = 0): StayQuote
    {
        $guests = $adults + $children;

        if ($adults < 1) {
            throw new InvalidArgumentException('At least one adult is required.');
        }

        if ($guests > $type->max_occupancy) {
            throw new InvalidArgumentException("{$type->name} sleeps at most {$type->max_occupancy}.");
        }

        $seasons = SeasonalRate::query()
            ->where('accommodation_type_id', $type->id)
            ->where('starts_on', '<', $stay->departureString())
            ->where('ends_on', '>=', $stay->arrivalString())
            ->get();

        $nightlyRates = [];

        foreach ($stay->nightDates() as $night) {
            $season = $seasons->first(
                fn (SeasonalRate $rate): bool => $night->betweenIncluded($rate->starts_on, $rate->ends_on),
            );

            $nightlyRates[$night->toDateString()] = $season?->nightly_rate_cents ?? $type->base_nightly_rate_cents;
        }

        return new StayQuote(
            nightlyRates: $nightlyRates,
            extraGuestPerNight: $type->extra_guest_nightly_cents ?? Money::zero(),
            extraGuests: max(0, $guests - $type->base_occupancy),
        );
    }
}
