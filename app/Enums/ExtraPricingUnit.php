<?php

declare(strict_types=1);

namespace App\Enums;

enum ExtraPricingUnit: string
{
    case PerStay = 'per_stay';
    case PerNight = 'per_night';
    case PerGuest = 'per_guest';
    case PerGuestPerNight = 'per_guest_per_night';

    public function label(): string
    {
        return match ($this) {
            self::PerStay => 'per stay',
            self::PerNight => 'per night',
            self::PerGuest => 'per guest',
            self::PerGuestPerNight => 'per guest per night',
        };
    }

    /**
     * How many units a booking needs.
     */
    public function quantityFor(int $nights, int $guests): int
    {
        return match ($this) {
            self::PerStay => 1,
            self::PerNight => $nights,
            self::PerGuest => $guests,
            self::PerGuestPerNight => $nights * $guests,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = ucfirst($case->label());
        }

        return $options;
    }
}
