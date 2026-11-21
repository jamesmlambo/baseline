<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kinds of bookable services (LSR-01).
 */
enum ServiceCategory: string
{
    case DayPass = 'day_pass';
    case Braai = 'braai';
    case Vip = 'vip';
    case Spa = 'spa';
    case Meal = 'meal';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DayPass => 'Pool / day pass',
            self::Braai => 'Braai',
            self::Vip => 'VIP area',
            self::Spa => 'Spa & massage',
            self::Meal => 'Meal',
            self::Other => 'Other',
        };
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
