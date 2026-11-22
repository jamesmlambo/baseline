<?php

declare(strict_types=1);

require_once __DIR__.'/BookingTestHelpers.php';

use App\Enums\StaffRole;
use App\Filament\Resources\AccommodationTypes\Pages\EditAccommodationType;
use App\Models\AccommodationType;
use App\Models\Room;
use Database\Seeders\AccommodationSeeder;
use Database\Seeders\DemoAccommodationSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('INV-01 seeds the client\'s nine room types and 25 rooms, hidden from guests until priced', function (): void {
    $this->seed(AccommodationSeeder::class);

    $roomCounts = AccommodationType::query()->withCount('rooms')->orderBy('sort_order')->pluck('rooms_count', 'name')->all();

    expect($roomCounts)->toBe([
        'Family Luxury Room' => 1,
        'Vista Room' => 1,
        'Studio Apartment Room' => 2,
        'Premium Room - Honeymoon Suite' => 1,
        'Premium Room - Villa Suite' => 1,
        'Budget Room' => 2,
        'Twin Double Bed Room' => 2,
        'Luxury Room' => 8,
        'Standard Room' => 7,
    ])
        ->and(AccommodationType::query()->where('is_active', true)->exists())->toBeFalse()
        ->and(AccommodationType::query()->where('base_nightly_rate_cents', '>', 0)->exists())->toBeFalse()
        ->and(Room::query()->where('is_demo', true)->exists())->toBeFalse()
        ->and(AccommodationType::query()->where('slug', 'luxury-room')->firstOrFail()->rooms()->orderBy('code')->pluck('code')->all())
        ->toEqualCanonicalizing(['19', '2', '3', '17', '9', '22', '15', '20'])
        ->and(Room::query()->where('code', 'SKYVIEW')->value('name'))->toBe('Skyview')
        ->and(Room::query()->where('code', 'VISTA')->first(['name', 'internal_notes'])->only(['name', 'internal_notes']))->toBe(['name' => 'Vista Room #1', 'internal_notes' => null])
        ->and(Room::query()->whereIn('code', ['FAMILY-LUXURY', 'HONEYMOON', 'VILLA'])->orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Family Luxury Room #1', 'Honeymoon Suite #1', 'Villa Suite #1'])
        ->and(Room::query()->where('code', 'TWIN-1')->value('internal_notes'))->toBe(AccommodationSeeder::PENDING_ROOM_NOTE);
});

it('INV-01 keeps the owner\'s prices, names and visibility when the seeder runs again', function (): void {
    $this->seed(AccommodationSeeder::class);
    AccommodationType::query()->where('slug', 'standard-room')->update(['base_nightly_rate_cents' => 95_000, 'is_active' => true]);
    Room::query()->where('code', 'VISTA')->update(['name' => 'Room 1']);

    $this->seed(AccommodationSeeder::class);

    $standard = AccommodationType::query()->where('slug', 'standard-room')->firstOrFail();

    expect($standard->base_nightly_rate_cents->cents)->toBe(95_000)
        ->and($standard->is_active)->toBeTrue()
        ->and(Room::query()->where('code', 'VISTA')->value('name'))->toBe('Room 1')
        ->and(Room::query()->count())->toBe(25);
});

it('INV-01 never switches deactivated demo rooms back on when demo data is seeded again', function (): void {
    app()['env'] = 'local';
    $this->seed(DemoAccommodationSeeder::class);
    AccommodationType::query()->where('is_demo', true)->update(['is_active' => false]);
    Room::query()->where('is_demo', true)->update(['is_active' => false]);

    $this->seed(DemoAccommodationSeeder::class);

    expect(AccommodationType::query()->where('is_demo', true)->where('is_active', true)->exists())->toBeFalse()
        ->and(Room::query()->where('is_demo', true)->where('is_active', true)->exists())->toBeFalse();
});

it('INV-01 refuses to make a room type bookable at R0.00, and allows it once a rate is set', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staffUser(StaffRole::Owner));
    $this->seed(AccommodationSeeder::class);
    $type = AccommodationType::query()->where('slug', 'budget-room')->firstOrFail();

    Livewire::test(EditAccommodationType::class, ['record' => $type->getRouteKey()])
        ->fillForm(['is_active' => true])
        ->call('save')
        ->assertHasFormErrors(['is_active']);

    expect($type->fresh()->is_active)->toBeFalse();

    Livewire::test(EditAccommodationType::class, ['record' => $type->getRouteKey()])
        ->fillForm(['base_nightly_rate_cents' => '650.00', 'is_active' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($type->fresh())
        ->is_active->toBeTrue()
        ->base_nightly_rate_cents->cents->toBe(65_000);
});
