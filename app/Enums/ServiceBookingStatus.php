<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceBookingStatus: string
{
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Booked',
            self::Completed => 'Done',
            self::NoShow => 'No-show',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Confirmed => 'info',
            self::Completed => 'success',
            self::NoShow => 'danger',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Holds capacity and resources.
     */
    public function isActive(): bool
    {
        return $this === self::Confirmed;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
