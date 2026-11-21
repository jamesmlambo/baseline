<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Task types from HK-01.
 */
enum TaskType: string
{
    case DailyClean = 'daily_clean';
    case LightRefresh = 'light_refresh';
    case CheckoutClean = 'checkout_clean';
    case DeepClean = 'deep_clean';
    case Gardening = 'gardening';
    case Maintenance = 'maintenance';
    case Pool = 'pool';
    case VipAreas = 'vip_areas';
    case BraaiArea = 'braai_area';
    case FishPond = 'fish_pond';
    case PressureWashing = 'pressure_washing';
    case FloorPolishing = 'floor_polishing';
    case Painting = 'painting';
    case Repairs = 'repairs';

    public function label(): string
    {
        return match ($this) {
            self::DailyClean => 'Daily clean',
            self::LightRefresh => 'Light refresh',
            self::CheckoutClean => 'Checkout clean',
            self::DeepClean => 'Monday deep clean',
            self::Gardening => 'Gardening',
            self::Maintenance => 'Maintenance',
            self::Pool => 'Pool',
            self::VipAreas => 'VIP areas',
            self::BraaiArea => 'Braai area',
            self::FishPond => 'Fish pond',
            self::PressureWashing => 'Pressure-washing',
            self::FloorPolishing => 'Floor polishing',
            self::Painting => 'Painting',
            self::Repairs => 'Repairs',
        };
    }

    /**
     * Housekeeping work, assigned by reception (HK-03). Everything else is assigned by the owner or a manager.
     */
    public function isHousekeeping(): bool
    {
        return in_array($this, [self::DailyClean, self::LightRefresh, self::CheckoutClean, self::DeepClean], true);
    }

    /**
     * Cleans that decide whether a room is ready for the next guest, so they need an inspection (HK-06).
     */
    public function needsInspection(): bool
    {
        return in_array($this, [self::CheckoutClean, self::DeepClean], true);
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
