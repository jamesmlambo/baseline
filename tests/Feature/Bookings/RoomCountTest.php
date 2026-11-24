<?php

declare(strict_types=1);

require_once __DIR__.'/BookingTestHelpers.php';

use App\Enums\StaffRole;
use App\Filament\Resources\AccommodationTypes\Pages\CreateAccommodationType;
use App\Filament\Resources\AccommodationTypes\Pages\EditAccommodationType;
use App\Filament\Resources\AccommodationTypes\Pages\ListAccommodationTypes;
use App\Models\AccommodationType;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staffUser(StaffRole::Owner));
    $this->travelTo(now('Africa/Johannesburg')->setDate(2027, 9, 1)->setTime(9, 0));
});

it('INV-01 adds a bookable room when the number of rooms is raised from 2 to 3', function (): void {
    $type = AccommodationType::factory()->withRooms(2)->create(['name' => 'Budget Room', 'slug' => 'budget-room']);

    Livewire::test(EditAccommodationType::class, ['record' => $type->getRouteKey()])
        ->assertSchemaStateSet(['room_count' => 2])
        ->fillForm(['room_count' => 3])
        ->call('save')
        ->assertHasNoFormErrors();

    $added = $type->rooms()->orderByDesc('id')->first();

    expect($type->rooms()->active()->count())->toBe(3)
        ->and($added->only(['name', 'code', 'is_active']))->toBe(['name' => 'Budget Room #3', 'code' => 'BUDGET-ROOM-3', 'is_active' => true]);

    Livewire::test(ListAccommodationTypes::class)
        ->assertTableColumnStateSet('rooms_count', 3, $type);
});

it('INV-01 creates the rooms when a room type is created', function (): void {
    Livewire::test(CreateAccommodationType::class)
        ->fillForm([
            'name' => 'Garden Suite',
            'slug' => 'garden-suite',
            'room_count' => 3,
            'base_occupancy' => 2,
            'max_occupancy' => 2,
            'base_nightly_rate_cents' => '900',
            'extra_guest_nightly_cents' => '0',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $type = AccommodationType::query()->where('slug', 'garden-suite')->firstOrFail();

    expect($type->rooms()->orderBy('sort_order')->pluck('name')->all())->toBe(['Garden Suite #1', 'Garden Suite #2', 'Garden Suite #3']);
});

it('INV-01 switches off rooms without upcoming bookings when the number is lowered, and switches them back on first when raised', function (): void {
    $type = AccommodationType::factory()->withRooms(3)->create();
    [$first, $second, $third] = $type->rooms()->orderBy('sort_order')->get()->all();
    bookType($type, stay('2027-09-10', '2027-09-12'), room: $third);

    Livewire::test(EditAccommodationType::class, ['record' => $type->getRouteKey()])
        ->fillForm(['room_count' => 2])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($second->fresh()->is_active)->toBeFalse()
        ->and($third->fresh()->is_active)->toBeTrue()
        ->and($first->fresh()->is_active)->toBeTrue();

    Livewire::test(EditAccommodationType::class, ['record' => $type->getRouteKey()])
        ->fillForm(['room_count' => 3])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($second->fresh()->is_active)->toBeTrue()
        ->and($type->rooms()->count())->toBe(3);
});

it('INV-01 refuses to remove booked rooms and saves none of the other changes', function (): void {
    $type = AccommodationType::factory()->withRooms(2)->create(['name' => 'Vista Room']);
    foreach ($type->rooms as $room) {
        bookType($type, stay('2027-09-10', '2027-09-12'), room: $room);
    }

    Livewire::test(EditAccommodationType::class, ['record' => $type->getRouteKey()])
        ->fillForm(['room_count' => 1, 'name' => 'Renamed'])
        ->call('save')
        ->assertHasErrors(['data.room_count']);

    expect($type->fresh()->name)->toBe('Vista Room')
        ->and($type->rooms()->active()->count())->toBe(2);
});

it('INV-01 lets past bookings stay on a room that is switched off', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();
    bookType($type, stay('2027-09-02', '2027-09-04'));
    $this->travelTo(now()->setDate(2027, 9, 10));

    Livewire::test(EditAccommodationType::class, ['record' => $type->getRouteKey()])
        ->fillForm(['room_count' => 0])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($type->rooms()->active()->count())->toBe(0)
        ->and($type->rooms()->count())->toBe(1);
});
