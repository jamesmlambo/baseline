<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ServiceCategory;
use App\Enums\ServiceResourceType;
use App\Models\Service;
use App\Models\ServiceResource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local-only DEMO leisure services so the booking screens can be reviewed. The service names come
 * from SPEC v5.3 (§3/§18); every price, hour and capacity here is a placeholder marked DEMO.
 */
class DemoLeisureSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoLeisureSeeder may only run in the local environment.');
        }

        $everyDay = fn (string $open, string $close): array => array_fill_keys(['1', '2', '3', '4', '5', '6', '7'], [$open, $close]);

        $services = [
            ['Pool day pass', ServiceCategory::DayPass, 480, 480, 5000, Service::PER_PERSON, Service::CAPACITY_PEOPLE, 30, [], $everyDay('09:00', '17:00')],
            ['Braai area', ServiceCategory::Braai, 180, 60, 15000, Service::PER_BOOKING, Service::CAPACITY_BOOKINGS, 2, [], $everyDay('11:00', '21:00')],
            ['VIP 1', ServiceCategory::Vip, 180, 60, 30000, Service::PER_BOOKING, Service::CAPACITY_BOOKINGS, 1, [], $everyDay('10:00', '22:00')],
            ['VIP 2', ServiceCategory::Vip, 180, 60, 30000, Service::PER_BOOKING, Service::CAPACITY_BOOKINGS, 1, [], $everyDay('10:00', '22:00')],
            ['Spa massage (60 min)', ServiceCategory::Spa, 60, 60, 45000, Service::PER_PERSON, Service::CAPACITY_PEOPLE, null, [ServiceResourceType::Therapist->value, ServiceResourceType::TreatmentRoom->value], $everyDay('09:00', '17:00')],
            ['Dinner', ServiceCategory::Meal, 90, 30, 18000, Service::PER_PERSON, Service::CAPACITY_PEOPLE, 20, [], $everyDay('18:00', '21:00')],
        ];

        foreach ($services as $index => [$name, $category, $duration, $interval, $price, $pricing, $unit, $capacity, $resources, $hours]) {
            Service::query()->updateOrCreate(['slug' => 'demo-'.Str::slug($name)], [
                'name' => "DEMO {$name}",
                'category' => $category,
                'description' => 'Placeholder service — prices, hours and capacity to be confirmed by the lodge.',
                'duration_minutes' => $duration,
                'slot_interval_minutes' => $interval,
                'price_cents' => $price,
                'pricing' => $pricing,
                'capacity_unit' => $unit,
                'capacity' => $capacity,
                'max_party_size' => $unit === Service::CAPACITY_BOOKINGS ? 20 : 6,
                'resource_types' => $resources,
                'opening_hours' => $hours,
                'is_demo' => true,
                'sort_order' => $index,
            ]);
        }

        foreach ([['DEMO Therapist A', ServiceResourceType::Therapist], ['DEMO Treatment room 1', ServiceResourceType::TreatmentRoom]] as [$name, $type]) {
            ServiceResource::query()->firstOrCreate(['name' => $name], ['type' => $type, 'is_demo' => true]);
        }
    }
}
