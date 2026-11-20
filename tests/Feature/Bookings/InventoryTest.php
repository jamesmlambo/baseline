<?php

declare(strict_types=1);

require_once __DIR__.'/BookingTestHelpers.php';

use App\Actions\Bookings\ReassignRoom;
use App\Actions\Inventory\BlockRoom;
use App\Actions\Inventory\ReleaseRoomBlock;
use App\Enums\RoomBlockReason;
use App\Enums\StaffRole;
use App\Exceptions\RoomUnavailableException;
use App\Models\AccommodationType;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\SeasonalRate;
use App\Services\Availability\AvailabilityService;
use App\Services\Pricing\RateCalculator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

it('INV-05 hides a blocked room from availability and frees it when released', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();
    $room = $type->rooms()->first();
    $availability = app(AvailabilityService::class);

    $block = app(BlockRoom::class)->handle($room, stay('2027-06-01', '2027-06-05'), RoomBlockReason::Maintenance, 'Geyser');

    expect($availability->availableRooms($type, stay('2027-06-03', '2027-06-04')))->toBeEmpty()
        ->and($availability->availableRooms($type, stay('2027-06-05', '2027-06-06')))->toHaveCount(1);

    app(ReleaseRoomBlock::class)->handle($block);

    expect($availability->availableRooms($type, stay('2027-06-03', '2027-06-04')))->toHaveCount(1)
        ->and($block->fresh()->released_at)->not->toBeNull();
});

it('refuses to block a room that already has a booking on those nights', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();
    bookType($type, stay('2027-06-02', '2027-06-04'));

    app(BlockRoom::class)->handle($type->rooms()->first(), stay('2027-06-01', '2027-06-03'), RoomBlockReason::Maintenance);
})->throws(RoomUnavailableException::class);

it('INV-07 moves a booking to another room with a reason and keeps the history', function (): void {
    $type = AccommodationType::factory()->withRooms(2)->create();
    $booking = bookType($type, stay('2027-06-10', '2027-06-12'));
    $current = $booking->activeBookingRooms->first();
    $target = $type->rooms()->whereKeyNot($current->room_id)->first();

    $replacement = app(ReassignRoom::class)->handle($current, $target, 'Leaking tap', staffUser(StaffRole::Manager));

    expect($replacement->room_id)->toBe($target->id)
        ->and($current->fresh()->released_at)->not->toBeNull()
        ->and($current->fresh()->release_reason)->toBe('reassigned')
        ->and(app(AvailabilityService::class)->isRoomAvailable($current->room, stay('2027-06-10', '2027-06-12')))->toBeTrue()
        ->and(Activity::query()->where('event', 'room_reassigned')->first()->properties['reason'])->toBe('Leaking tap');
});

it('refuses to move a booking into an occupied room and keeps the original assignment', function (): void {
    $type = AccommodationType::factory()->withRooms(2)->create();
    $first = bookType($type, stay('2027-06-10', '2027-06-12'));
    $second = bookType($type, stay('2027-06-10', '2027-06-12'));
    $firstRoom = $first->activeBookingRooms->first();

    expect(fn () => app(ReassignRoom::class)->handle($firstRoom, $second->activeBookingRooms->first()->room, 'Upgrade'))
        ->toThrow(RoomUnavailableException::class);

    expect($firstRoom->fresh()->released_at)->toBeNull()
        ->and($first->activeBookingRooms()->count())->toBe(1);
});

it('requires a reason to move a booking', function (): void {
    $type = AccommodationType::factory()->withRooms(2)->create();
    $booking = bookType($type, stay('2027-06-10', '2027-06-12'));
    $current = $booking->activeBookingRooms->first();

    app(ReassignRoom::class)->handle($current, $type->rooms()->whereKeyNot($current->room_id)->first(), '  ');
})->throws(InvalidArgumentException::class);

it('prices each night from the season covering it, otherwise the base rate', function (): void {
    $type = AccommodationType::factory()->create(['base_nightly_rate_cents' => 100_000]);
    SeasonalRate::factory()->for($type)->create([
        'starts_on' => '2027-12-15',
        'ends_on' => '2027-12-31',
        'nightly_rate_cents' => 180_000,
    ]);

    $quote = app(RateCalculator::class)->quote($type, stay('2027-12-14', '2027-12-17'), 2);

    expect(array_map(fn ($money) => $money->cents, $quote->nightlyRates))->toBe([
        '2027-12-14' => 100_000,
        '2027-12-15' => 180_000,
        '2027-12-16' => 180_000,
    ])->and($quote->total()->cents)->toBe(460_000);
});

it('lets the database refuse overlapping seasons for one type', function (): void {
    $type = AccommodationType::factory()->create();
    SeasonalRate::factory()->for($type)->create(['starts_on' => '2027-12-01', 'ends_on' => '2027-12-20']);
    SeasonalRate::factory()->for($type)->create(['starts_on' => '2027-12-20', 'ends_on' => '2027-12-31']);
})->throws(QueryException::class);

it('refuses a party larger than the room sleeps', function (): void {
    $type = AccommodationType::factory()->create(['max_occupancy' => 2]);

    app(RateCalculator::class)->quote($type, stay('2027-06-01', '2027-06-02'), 3);
})->throws(InvalidArgumentException::class);

it('search lists only types that fit the party, with live counts and prices', function (): void {
    $small = AccommodationType::factory()->withRooms(2)->create(['max_occupancy' => 2, 'sort_order' => 1]);
    $family = AccommodationType::factory()->withRooms(1)->create(['max_occupancy' => 5, 'sort_order' => 2]);
    bookType($small, stay('2027-07-01', '2027-07-03'));

    $results = app(AvailabilityService::class)->search(stay('2027-07-01', '2027-07-03'), 2);
    $familyOnly = app(AvailabilityService::class)->search(stay('2027-07-01', '2027-07-03'), 4);

    expect($results)->toHaveCount(2)
        ->and($results[0]->type->id)->toBe($small->id)
        ->and($results[0]->availableRooms)->toBe(1)
        ->and($familyOnly)->toHaveCount(1)
        ->and($familyOnly[0]->type->id)->toBe($family->id);
});

it('INV-03 lets the database refuse a second active claim on the same room night', function (): void {
    $room = Room::factory()->create();
    $block = app(BlockRoom::class)->handle($room, stay('2027-08-01', '2027-08-02'), RoomBlockReason::Other);

    DB::table('room_nights')->insert([
        'room_id' => $room->id,
        'night_date' => '2027-08-01',
        'room_block_id' => $block->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('INV-04 lets the database refuse overlapping active assignments for one room', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();
    $booking = bookType($type, stay('2027-08-10', '2027-08-13'));
    $existing = $booking->activeBookingRooms->first();

    BookingRoom::query()->create([
        'booking_id' => $booking->id,
        'accommodation_type_id' => $type->id,
        'room_id' => $existing->room_id,
        'arrival_date' => '2027-08-12',
        'departure_date' => '2027-08-14',
        'adults' => 1,
        'accommodation_total_cents' => 0,
    ]);
})->throws(QueryException::class);
