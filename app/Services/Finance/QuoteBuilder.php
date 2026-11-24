<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\AccommodationType;
use App\Models\Extra;
use App\Models\Service;
use App\Services\Pricing\RateCalculator;
use App\Support\Money;
use App\Support\StayDates;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Turns what staff pick on the quotation form into priced lines (PAY-01). Prices always come
 * from the system: room types through the rate engine (seasonal rates included), extras and
 * leisure services (spa, day pass…) from their price lists. Staff never type a price.
 */
class QuoteBuilder
{
    public function __construct(private readonly RateCalculator $rates) {}

    /**
     * Prices the selections stored on a quotation (`quotations.items`) at today's prices.
     *
     * @param  array{arrival?: string|null, departure?: string|null, rooms?: list<array<string, mixed>>, extras?: list<array<string, mixed>>, services?: list<array<string, mixed>>}  $items
     * @return list<array{description: string, quantity: int, unit: Money, total: Money}>
     */
    public function linesForItems(array $items): array
    {
        $stay = filled($items['arrival'] ?? null) && filled($items['departure'] ?? null)
            ? new StayDates($items['arrival'], $items['departure'])
            : null;

        return $this->lines($stay, $items['rooms'] ?? [], $items['extras'] ?? [], $items['services'] ?? []);
    }

    /**
     * @param  list<array{total: Money}>  $lines
     */
    public static function total(array $lines): Money
    {
        return array_reduce($lines, fn (Money $carry, array $line): Money => $carry->add($line['total']), Money::zero());
    }

    /**
     * @param  list<array{accommodation_type_id: int|string, rooms?: int|string|null, adults: int|string, children?: int|string|null}>  $rooms
     * @param  list<array{extra_id: int|string, quantity?: int|string|null}>  $extras
     * @param  list<array{service_id: int|string, people?: int|string|null, sessions?: int|string|null}>  $services
     * @return list<array{description: string, quantity: int, unit: Money, total: Money}>
     */
    public function lines(?StayDates $stay, array $rooms, array $extras, array $services): array
    {
        if ($rooms !== [] && $stay === null) {
            throw new InvalidArgumentException('Choose arrival and departure dates to quote rooms.');
        }

        $lines = [];
        $guests = 0;

        foreach ($rooms as $row) {
            $type = AccommodationType::query()->findOrFail($row['accommodation_type_id']);
            $count = max(1, (int) ($row['rooms'] ?? 1));
            $adults = (int) $row['adults'];
            $children = (int) ($row['children'] ?? 0);

            if ($type->base_nightly_rate_cents->isZero()) {
                throw new InvalidArgumentException("{$type->name} has no price yet. Set its rate under Rooms & rates first.");
            }

            $stayPrice = $this->rates->quote($type, $stay, $adults, $children)->total();
            $guests += ($adults + $children) * $count;
            $nights = $stay->nights();

            $lines[] = [
                'description' => sprintf(
                    '%s, %s – %s (%d %s, %d %s per room)',
                    $type->name,
                    $stay->arrival->format('j M Y'),
                    $stay->departure->format('j M Y'),
                    $nights,
                    Str::plural('night', $nights),
                    $adults + $children,
                    Str::plural('guest', $adults + $children),
                ),
                'quantity' => $count,
                'unit' => $stayPrice,
                'total' => $stayPrice->multiply($count),
            ];
        }

        foreach ($extras as $row) {
            $extra = Extra::query()->findOrFail($row['extra_id']);
            $quantity = filled($row['quantity'] ?? null)
                ? (int) $row['quantity']
                : $extra->pricing_unit->quantityFor($stay?->nights() ?? 1, max(1, $guests));

            $lines[] = $this->line("{$extra->name} ({$extra->pricing_unit->label()})", $quantity, $extra->price_cents);
        }

        foreach ($services as $row) {
            $service = Service::query()->findOrFail($row['service_id']);
            $people = max(1, (int) ($row['people'] ?? 1));
            $sessions = max(1, (int) ($row['sessions'] ?? 1));
            $perPerson = $service->pricing === Service::PER_PERSON;

            $description = $service->name.($perPerson ? ", {$people} ".Str::plural('person', $people) : '').($sessions > 1 ? ", {$sessions} sessions" : '');

            $lines[] = $this->line($description, ($perPerson ? $people : 1) * $sessions, $service->price_cents);
        }

        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one room, extra or service to the quotation.');
        }

        return $lines;
    }

    /**
     * @return array{description: string, quantity: int, unit: Money, total: Money}
     */
    private function line(string $description, int $quantity, Money $unit): array
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException("Enter a quantity of at least 1 for {$description}.");
        }

        return ['description' => $description, 'quantity' => $quantity, 'unit' => $unit, 'total' => $unit->multiply($quantity)];
    }
}
