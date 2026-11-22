<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ExtraPricingUnit;
use App\Models\AccommodationType;
use App\Models\Extra;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local-only DEMO room types and rooms so the booking engine can be clicked through.
 * Names and prices are placeholders (PEND-04). They are flagged is_demo and must be
 * replaced with the client's real inventory before launch.
 */
class DemoAccommodationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoAccommodationSeeder may only run in the local environment.');
        }

        $types = [
            ['name' => 'Demo Standard Room', 'slug' => 'demo-standard-room', 'base' => 2, 'max' => 2, 'rate' => 90_000, 'extra' => 0, 'rooms' => 4],
            ['name' => 'Demo Family Room', 'slug' => 'demo-family-room', 'base' => 2, 'max' => 4, 'rate' => 140_000, 'extra' => 25_000, 'rooms' => 2],
        ];

        $roomNumber = 1;

        foreach ($types as $index => $definition) {
            $type = AccommodationType::query()->firstOrNew(['slug' => $definition['slug']]);
            $type->fill([
                'name' => $definition['name'],
                'summary' => 'DEMO — replace before launch.',
                'description' => 'Placeholder description. The client has not yet supplied room names, photos, amenities or prices.',
                'base_occupancy' => $definition['base'],
                'max_occupancy' => $definition['max'],
                'base_nightly_rate_cents' => $definition['rate'],
                'extra_guest_nightly_cents' => $definition['extra'],
                'sort_order' => $index + 1,
                'is_demo' => true,
            ]);
            $this->activateIfNew($type);
            $type->save();

            $this->seedExtras();

            for ($i = 0; $i < $definition['rooms']; $i++, $roomNumber++) {
                $room = $type->rooms()->firstOrNew(['code' => sprintf('DEMO%02d', $roomNumber)]);
                $room->fill([
                    'name' => sprintf('Demo Room %02d', $roomNumber),
                    'sort_order' => $roomNumber,
                    'is_demo' => true,
                ]);
                $this->activateIfNew($room);
                $room->save();
            }
        }
    }

    /**
     * New demo records start bookable. Re-seeding never switches a deactivated one back on,
     * so demo rooms stay hidden once the real rooms have replaced them.
     */
    private function activateIfNew(Model $record): void
    {
        if (! $record->exists) {
            $record->is_active = true;
        }
    }

    /**
     * Breakfast and dinner are named in SPEC v5.3 §7; the prices here are placeholders.
     */
    private function seedExtras(): void
    {
        foreach ([
            ['Demo breakfast', ExtraPricingUnit::PerGuestPerNight, 15_000, 1],
            ['Demo dinner', ExtraPricingUnit::PerGuestPerNight, 25_000, 2],
        ] as [$name, $unit, $price, $order]) {
            Extra::query()->updateOrCreate(['name' => $name], [
                'description' => 'DEMO — price and menu to be confirmed.',
                'pricing_unit' => $unit,
                'price_cents' => $price,
                'is_active' => true,
                'is_bookable_online' => true,
                'is_demo' => true,
                'sort_order' => $order,
            ]);
        }
    }
}
