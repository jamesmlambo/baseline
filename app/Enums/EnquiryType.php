<?php

declare(strict_types=1);

namespace App\Enums;

enum EnquiryType: string
{
    case Contact = 'contact';
    case BookingRequest = 'booking_request';

    public function label(): string
    {
        return match ($this) {
            self::Contact => 'Contact form',
            self::BookingRequest => 'Booking request',
        };
    }
}
