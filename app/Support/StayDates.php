<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Arrival (inclusive) and departure (exclusive) dates for a stay. A stay 10–12 covers the nights of the 10th and 11th.
 */
final readonly class StayDates
{
    public CarbonImmutable $arrival;

    public CarbonImmutable $departure;

    public function __construct(CarbonInterface|string $arrival, CarbonInterface|string $departure)
    {
        $this->arrival = CarbonImmutable::parse($arrival)->startOfDay();
        $this->departure = CarbonImmutable::parse($departure)->startOfDay();

        if ($this->departure->lessThanOrEqualTo($this->arrival)) {
            throw new InvalidArgumentException('Departure must be after arrival.');
        }
    }

    public function nights(): int
    {
        return (int) $this->arrival->diffInDays($this->departure);
    }

    /**
     * @return list<CarbonImmutable>
     */
    public function nightDates(): array
    {
        $dates = [];

        for ($date = $this->arrival; $date->lessThan($this->departure); $date = $date->addDay()) {
            $dates[] = $date;
        }

        return $dates;
    }

    public function arrivalString(): string
    {
        return $this->arrival->toDateString();
    }

    public function departureString(): string
    {
        return $this->departure->toDateString();
    }
}
