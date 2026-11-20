<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a booking came from (BOOK-01, BOOK-05).
 */
enum BookingSource: string
{
    case Website = 'website';
    case WalkIn = 'walk_in';
    case Phone = 'phone';
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case BookingCom = 'booking_com';
    case LekkeSlaap = 'lekkeslaap';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::WalkIn => 'Walk-in',
            self::Phone => 'Phone',
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp price list',
            self::BookingCom => 'Booking.com',
            self::LekkeSlaap => 'LekkeSlaap',
            self::Other => 'Other',
        };
    }

    public function isExternalChannel(): bool
    {
        return in_array($this, [self::BookingCom, self::LekkeSlaap], true);
    }
}
