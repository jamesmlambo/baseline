<?php

declare(strict_types=1);

use App\Actions\Bookings\Data\BookingRequest;
use App\Actions\Bookings\Data\RoomRequest;
use App\Actions\Bookings\PlaceBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Exceptions\RoomUnavailableException;
use App\Models\AccommodationType;
use App\Models\Booking;
use App\Models\RoomNight;
use App\Support\StayDates;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| These tests commit real data on two separate PostgreSQL connections, so they use
| DatabaseTruncation instead of RefreshDatabase (configured in tests/Pest.php).
*/

beforeEach(function (): void {
    config(['database.connections.pgsql_rival' => config('database.connections.pgsql')]);
});

afterEach(function (): void {
    DB::disconnect('pgsql_rival');
});

function placeLastRoomBooking(AccommodationType $type, string $guest): Booking
{
    return app(PlaceBooking::class)->handle(new BookingRequest(
        stay: new StayDates('2027-09-10', '2027-09-12'),
        rooms: [new RoomRequest($type, 2)],
        leadGuestName: $guest,
        source: BookingSource::Website,
        status: BookingStatus::Hold,
    ));
}

it('INV-03 makes a rival connection wait, then fail, while another booking holds the last room', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();
    $room = $type->rooms()->first();

    // A rival process has created its own booking row and is about to claim the same nights.
    $rivalBookingRoomId = DB::connection('pgsql_rival')->transaction(function () use ($type, $room): int {
        $bookingId = DB::connection('pgsql_rival')->table('bookings')->insertGetId([
            'reference' => 'BL-RIVAL1',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'source' => 'phone',
            'arrival_date' => '2027-09-10',
            'departure_date' => '2027-09-12',
            'lead_guest_name' => 'Rival Guest',
            'total_cents' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::connection('pgsql_rival')->table('booking_rooms')->insertGetId([
            'booking_id' => $bookingId,
            'accommodation_type_id' => $type->id,
            'room_id' => $room->id,
            'arrival_date' => '2027-09-10',
            'departure_date' => '2027-09-11',
            'adults' => 2,
            'accommodation_total_cents' => 0,
            'released_at' => now(),
            'release_reason' => 'test-fixture',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    // Connection 1 places the real booking but has NOT committed yet.
    DB::beginTransaction();
    $winner = placeLastRoomBooking($type, 'First Guest');

    // Connection 2 tries to claim the same room night at the same moment. It must wait for
    // connection 1's uncommitted claim, so with a short lock timeout it gives up.
    $rival = DB::connection('pgsql_rival');
    $rival->statement("SET lock_timeout = '750ms'");

    $claim = fn () => $rival->table('room_nights')->insert([
        'room_id' => $room->id,
        'night_date' => '2027-09-10',
        'booking_room_id' => $rivalBookingRoomId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($claim)->toThrow(QueryException::class, 'lock timeout');

    // Connection 1 commits. Now the rival's claim is refused outright as a duplicate.
    DB::commit();

    expect($claim)->toThrow(QueryException::class, 'room_nights_one_active_claim');

    expect(RoomNight::query()->active()->where('room_id', $room->id)->count())->toBe(2)
        ->and($winner->fresh()->status)->toBe(BookingStatus::Hold);
});

it('INV-03 lets exactly one of two bookings for the last room succeed', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();

    $first = placeLastRoomBooking($type, 'First Guest');

    expect(fn () => placeLastRoomBooking($type, 'Second Guest'))
        ->toThrow(RoomUnavailableException::class, 'no longer available');

    expect(Booking::query()->count())->toBe(1)
        ->and($first->activeBookingRooms)->toHaveCount(1);
});
