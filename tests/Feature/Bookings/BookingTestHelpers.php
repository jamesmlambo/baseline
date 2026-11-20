<?php

declare(strict_types=1);

use App\Actions\Bookings\Data\BookingRequest;
use App\Actions\Bookings\Data\RoomRequest;
use App\Actions\Bookings\PlaceBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\AccommodationType;
use App\Models\Booking;
use App\Models\Room;
use App\Support\StayDates;

function stay(string $arrival, string $departure): StayDates
{
    return new StayDates($arrival, $departure);
}

function bookType(
    AccommodationType $type,
    StayDates $stay,
    BookingStatus $status = BookingStatus::Confirmed,
    int $adults = 2,
    int $children = 0,
    ?Room $room = null,
): Booking {
    return app(PlaceBooking::class)->handle(new BookingRequest(
        stay: $stay,
        rooms: [new RoomRequest($type, $adults, $children, $room)],
        leadGuestName: 'Demo Guest',
        source: BookingSource::Phone,
        status: $status,
    ));
}
