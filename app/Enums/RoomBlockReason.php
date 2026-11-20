<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a room is taken out of inventory (INV-05).
 */
enum RoomBlockReason: string
{
    case Maintenance = 'maintenance';
    case OwnerUse = 'owner_use';
    case OutOfService = 'out_of_service';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Maintenance',
            self::OwnerUse => 'Owner use',
            self::OutOfService => 'Out of service',
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
