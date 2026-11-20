<?php

declare(strict_types=1);

require_once __DIR__.'/BookingTestHelpers.php';

use App\Enums\StaffRole;
use App\Filament\Resources\AccommodationTypes\Pages\CreateAccommodationType;
use App\Filament\Resources\AccommodationTypes\Pages\EditAccommodationType;
use App\Filament\Resources\AccommodationTypes\Pages\ListAccommodationTypes;
use App\Filament\Resources\AccommodationTypes\RelationManagers\RoomsRelationManager;
use App\Filament\Resources\AccommodationTypes\RelationManagers\SeasonalRatesRelationManager;
use App\Filament\Resources\RoomBlocks\Pages\CreateRoomBlock;
use App\Models\AccommodationType;
use App\Models\RoomBlock;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('lets reception see rooms and rates but not change them', function (): void {
    $receptionist = staffUser(StaffRole::Receptionist);
    $type = AccommodationType::factory()->create();

    expect($receptionist->can('viewAny', AccommodationType::class))->toBeTrue()
        ->and($receptionist->can('create', AccommodationType::class))->toBeFalse()
        ->and($receptionist->can('update', $type))->toBeFalse();
});

it('never allows room types or rooms to be deleted', function (): void {
    $owner = staffUser(StaffRole::Owner);
    $type = AccommodationType::factory()->withRooms(1)->create();

    expect($owner->can('delete', $type))->toBeFalse()
        ->and($owner->can('delete', $type->rooms()->first()))->toBeFalse();
});

it('lists room types for a manager', function (): void {
    $this->actingAs(staffUser(StaffRole::Manager));
    $types = AccommodationType::factory()->count(2)->create();

    Livewire::test(ListAccommodationTypes::class)
        ->assertOk()
        ->assertCanSeeTableRecords($types);
});

it('creates a room type from rand amounts typed by staff', function (): void {
    $this->actingAs(staffUser(StaffRole::Owner));

    Livewire::test(CreateAccommodationType::class)
        ->fillForm([
            'name' => 'Garden Suite',
            'slug' => 'garden-suite',
            'base_occupancy' => 2,
            'max_occupancy' => 3,
            'base_nightly_rate_cents' => '1 250.50',
            'extra_guest_nightly_cents' => '200',
            'is_active' => true,
            'is_demo' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $type = AccommodationType::query()->where('slug', 'garden-suite')->firstOrFail();

    expect($type->base_nightly_rate_cents->cents)->toBe(125_050)
        ->and($type->extra_guest_nightly_cents->cents)->toBe(20_000);
});

it('renders the edit screen with its rooms and seasonal-rates tabs', function (): void {
    $this->actingAs(staffUser(StaffRole::Owner));
    $type = AccommodationType::factory()->withRooms(2)->create();

    Livewire::test(EditAccommodationType::class, ['record' => $type->getRouteKey()])
        ->assertOk()
        ->assertSchemaStateSet(['base_nightly_rate_cents' => '1000.00']);

    Livewire::test(RoomsRelationManager::class, [
        'ownerRecord' => $type,
        'pageClass' => EditAccommodationType::class,
    ])->assertOk()->assertCanSeeTableRecords($type->rooms);

    Livewire::test(SeasonalRatesRelationManager::class, [
        'ownerRecord' => $type,
        'pageClass' => EditAccommodationType::class,
    ])->assertOk();
});

it('refuses a maximum below the guests included in the rate', function (): void {
    $this->actingAs(staffUser(StaffRole::Owner));

    Livewire::test(CreateAccommodationType::class)
        ->fillForm([
            'name' => 'Odd Room',
            'slug' => 'odd-room',
            'base_occupancy' => 3,
            'max_occupancy' => 2,
            'base_nightly_rate_cents' => '900',
            'extra_guest_nightly_cents' => '0',
        ])
        ->call('create')
        ->assertHasFormErrors(['max_occupancy']);
});

it('blocks a room from the admin screen', function (): void {
    $this->actingAs(staffUser(StaffRole::Manager));
    $type = AccommodationType::factory()->withRooms(1)->create();
    $room = $type->rooms()->first();

    Livewire::test(CreateRoomBlock::class)
        ->fillForm([
            'room_id' => $room->id,
            'starts_on' => '2027-10-01',
            'ends_on' => '2027-10-03',
            'reason' => 'maintenance',
            'notes' => 'Painting',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(RoomBlock::query()->where('room_id', $room->id)->count())->toBe(1);
});

it('explains why a booked room cannot be blocked and saves nothing', function (): void {
    $this->actingAs(staffUser(StaffRole::Manager));
    $type = AccommodationType::factory()->withRooms(1)->create();
    $room = $type->rooms()->first();
    bookType($type, stay('2027-10-02', '2027-10-04'));

    Livewire::test(CreateRoomBlock::class)
        ->fillForm([
            'room_id' => $room->id,
            'starts_on' => '2027-10-01',
            'ends_on' => '2027-10-03',
            'reason' => 'maintenance',
        ])
        ->call('create')
        ->assertNotified('Room not blocked');

    expect(RoomBlock::query()->count())->toBe(0);
});
