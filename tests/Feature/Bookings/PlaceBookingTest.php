<?php

declare(strict_types=1);

require_once __DIR__.'/BookingTestHelpers.php';

use App\Actions\Bookings\Data\BookingRequest;
use App\Actions\Bookings\Data\RoomRequest;
use App\Actions\Bookings\PlaceBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Exceptions\RoomUnavailableException;
use App\Models\AccommodationType;
use App\Models\Booking;
use App\Models\RoomNight;
use App\Services\Availability\AvailabilityService;

it('INV-02 assigns a specific physical room at booking time', function (): void {
    $type = AccommodationType::factory()->withRooms(2)->create();

    $booking = bookType($type, stay('2027-03-10', '2027-03-13'));

    expect($booking->activeBookingRooms)->toHaveCount(1)
        ->and($booking->activeBookingRooms->first()->room->accommodation_type_id)->toBe($type->id)
        ->and($booking->reference)->toMatch('/^BL-[A-HJ-NP-Z2-9]{6}$/');
});

it('INV-03 claims one room night per night of the stay', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();

    $booking = bookType($type, stay('2027-03-10', '2027-03-13'));

    $nights = RoomNight::query()->active()->orderBy('night_date')->pluck('night_date')->map->toDateString()->all();

    expect($nights)->toBe(['2027-03-10', '2027-03-11', '2027-03-12'])
        ->and($booking->nights())->toBe(3);
});

it('fills different rooms for overlapping bookings and refuses when the type is full', function (): void {
    $type = AccommodationType::factory()->withRooms(2)->create();

    $first = bookType($type, stay('2027-03-10', '2027-03-12'));
    $second = bookType($type, stay('2027-03-11', '2027-03-13'));

    expect($first->activeBookingRooms->first()->room_id)
        ->not->toBe($second->activeBookingRooms->first()->room_id);

    bookType($type, stay('2027-03-11', '2027-03-12'));
})->throws(RoomUnavailableException::class);

it('allows a new arrival on the same day another guest departs', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();

    bookType($type, stay('2027-03-10', '2027-03-12'));
    $next = bookType($type, stay('2027-03-12', '2027-03-14'));

    expect($next->status)->toBe(BookingStatus::Confirmed);
});

it('refuses a specific room that is already taken', function (): void {
    $type = AccommodationType::factory()->withRooms(2)->create();
    $room = $type->rooms()->first();

    bookType($type, stay('2027-03-10', '2027-03-12'), room: $room);
    bookType($type, stay('2027-03-11', '2027-03-12'), room: $room);
})->throws(RoomUnavailableException::class);

it('leaves nothing behind when a multi-room booking cannot be completed', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();

    expect(fn () => app(PlaceBooking::class)->handle(new BookingRequest(
        stay: stay('2027-03-10', '2027-03-12'),
        rooms: [
            new RoomRequest($type, 2),
            new RoomRequest($type, 2),
        ],
        leadGuestName: 'Demo Group',
        source: BookingSource::Email,
    )))->toThrow(RoomUnavailableException::class);

    expect(Booking::query()->count())->toBe(0)
        ->and(RoomNight::query()->count())->toBe(0);
});

it('BOOK-04 gives every hold an expiry time', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();

    $booking = bookType($type, stay('2027-03-10', '2027-03-12'), BookingStatus::Hold);

    expect($booking->hold_expires_at)->not->toBeNull()
        ->and($booking->hold_expires_at->isFuture())->toBeTrue();
});

it('totals the booking from the rate engine', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create([
        'base_nightly_rate_cents' => 95_000,
        'base_occupancy' => 2,
        'extra_guest_nightly_cents' => 15_000,
    ]);

    $booking = bookType($type, stay('2027-03-10', '2027-03-12'), adults: 3);

    // 2 nights x R950 + 1 extra guest x 2 nights x R150
    expect($booking->total_cents->cents)->toBe(220_000)
        ->and($booking->total_cents->format())->toBe('R 2 200.00');
});

it('records the first status in the booking history', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();

    $booking = bookType($type, stay('2027-03-10', '2027-03-12'), BookingStatus::Hold);

    expect($booking->statusHistory)->toHaveCount(1)
        ->and($booking->statusHistory->first()->to_status)->toBe(BookingStatus::Hold);
});

it('never offers inactive rooms', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();
    $type->rooms()->update(['is_active' => false]);

    expect(app(AvailabilityService::class)->availableRooms($type, stay('2027-03-10', '2027-03-12')))->toBeEmpty();
});
