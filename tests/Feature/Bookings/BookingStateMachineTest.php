<?php

declare(strict_types=1);

require_once __DIR__.'/BookingTestHelpers.php';

use App\Actions\Bookings\ReleaseExpiredHolds;
use App\Enums\BookingStatus;
use App\Enums\StaffRole;
use App\Exceptions\InvalidBookingTransitionException;
use App\Models\AccommodationType;
use App\Models\RoomNight;
use App\Services\Bookings\BookingStateMachine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;

it('BOOK-03 rejects invalid status changes', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();
    $booking = bookType($type, stay('2027-04-01', '2027-04-03'), BookingStatus::Hold);

    app(BookingStateMachine::class)->transition($booking, BookingStatus::CheckedOut);
})->throws(InvalidBookingTransitionException::class);

it('BOOK-03 records every status change with who made it and why', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();
    $booking = bookType($type, stay('2027-04-01', '2027-04-03'), BookingStatus::Hold);
    $receptionist = staffUser(StaffRole::Receptionist);

    app(BookingStateMachine::class)->transition($booking, BookingStatus::Confirmed, 'Deposit verified', $receptionist);

    $last = $booking->statusHistory()->get()->last();

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($last->from_status)->toBe(BookingStatus::Hold)
        ->and($last->to_status)->toBe(BookingStatus::Confirmed)
        ->and($last->reason)->toBe('Deposit verified')
        ->and($last->changed_by)->toBe($receptionist->id);
});

it('frees the room immediately when a booking is cancelled', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();
    $booking = bookType($type, stay('2027-04-01', '2027-04-03'));

    app(BookingStateMachine::class)->transition($booking, BookingStatus::Cancelled, 'Guest cancelled');

    expect(RoomNight::query()->active()->count())->toBe(0)
        ->and(RoomNight::query()->count())->toBe(2)
        ->and($booking->bookingRooms()->first()->release_reason)->toBe('cancelled');

    $rebooked = bookType($type, stay('2027-04-01', '2027-04-03'));
    expect($rebooked->status)->toBe(BookingStatus::Confirmed);
});

it('keeps the room claimed through check-in and check-out', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();
    $booking = bookType($type, stay('2027-04-01', '2027-04-03'));
    $machine = app(BookingStateMachine::class);

    $machine->transition($booking, BookingStatus::CheckedIn);
    $machine->transition($booking, BookingStatus::CheckedOut);

    expect(RoomNight::query()->active()->count())->toBe(2);
});

it('BOOK-04 expires holds past their expiry and frees the rooms', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();
    $expired = bookType($type, stay('2027-04-01', '2027-04-03'), BookingStatus::Hold);
    $expired->forceFill(['hold_expires_at' => now()->subMinute()])->save();

    $live = bookType($type, stay('2027-05-01', '2027-05-03'), BookingStatus::Hold);

    $count = app(ReleaseExpiredHolds::class)->handle();

    expect($count)->toBe(1)
        ->and($expired->fresh()->status)->toBe(BookingStatus::Expired)
        ->and($live->fresh()->status)->toBe(BookingStatus::Hold)
        ->and(RoomNight::query()->active()->count())->toBe(2);
});

it('runs hold expiry from the scheduled command', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();
    $hold = bookType($type, stay('2027-04-01', '2027-04-03'), BookingStatus::Hold);
    $hold->forceFill(['hold_expires_at' => now()->subMinute()])->save();

    Artisan::call('bookings:release-expired-holds');

    expect($hold->fresh()->status)->toBe(BookingStatus::Expired);
});

it('lets the database refuse a hold without an expiry', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();
    $hold = bookType($type, stay('2027-04-01', '2027-04-03'), BookingStatus::Hold);

    $hold->forceFill(['hold_expires_at' => null])->save();
})->throws(QueryException::class);
