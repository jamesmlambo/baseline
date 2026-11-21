<?php

declare(strict_types=1);

require_once __DIR__.'/../Portal/PortalTestHelpers.php';

use App\Enums\ServiceCategory;
use App\Enums\ServiceResourceType;
use App\Models\Service;
use App\Models\ServiceResource;
use Carbon\CarbonImmutable;

/**
 * @param  array<string, mixed>  $overrides
 */
function leisureService(array $overrides = []): Service
{
    static $counter = 0;
    $counter++;

    return Service::query()->create(array_merge([
        'name' => "Test service {$counter}",
        'slug' => "test-service-{$counter}",
        'category' => ServiceCategory::DayPass,
        'duration_minutes' => 60,
        'slot_interval_minutes' => 60,
        'price_cents' => 10_000,
        'pricing' => Service::PER_PERSON,
        'capacity_unit' => Service::CAPACITY_PEOPLE,
        'capacity' => 10,
        'max_party_size' => 6,
        'cancellation_cutoff_hours' => 24,
        'opening_hours' => array_fill_keys(['1', '2', '3', '4', '5', '6', '7'], ['09:00', '17:00']),
        'is_demo' => true,
    ], $overrides));
}

/**
 * A spa treatment needing a therapist and a treatment room, with the given numbers of each.
 */
function spaService(int $therapists = 1, int $rooms = 1): Service
{
    foreach (range(1, $therapists) as $index) {
        ServiceResource::query()->create(['name' => "Therapist {$index}", 'type' => ServiceResourceType::Therapist]);
    }

    foreach (range(1, $rooms) as $index) {
        ServiceResource::query()->create(['name' => "Room {$index}", 'type' => ServiceResourceType::TreatmentRoom]);
    }

    return leisureService([
        'category' => ServiceCategory::Spa,
        'capacity' => null,
        'resource_types' => [ServiceResourceType::Therapist->value, ServiceResourceType::TreatmentRoom->value],
    ]);
}

function at(string $local): CarbonImmutable
{
    return CarbonImmutable::parse($local, 'Africa/Johannesburg');
}
