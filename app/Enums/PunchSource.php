<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a clock event came from (ATT-01).
 */
enum PunchSource: string
{
    case Hikvision = 'hikvision';
    case Csv = 'csv';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Hikvision => 'Hikvision terminal',
            self::Csv => 'CSV import',
            self::Manual => 'Manual entry',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Hikvision => 'info',
            self::Csv => 'gray',
            self::Manual => 'warning',
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
