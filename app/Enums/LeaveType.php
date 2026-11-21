<?php

declare(strict_types=1);

namespace App\Enums;

enum LeaveType: string
{
    case Annual = 'annual';
    case Sick = 'sick';
    case Family = 'family';
    case Unpaid = 'unpaid';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Annual => 'Annual leave',
            self::Sick => 'Sick leave',
            self::Family => 'Family responsibility',
            self::Unpaid => 'Unpaid leave',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Annual => 'info',
            self::Sick => 'warning',
            self::Family => 'info',
            self::Unpaid => 'gray',
            self::Other => 'gray',
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
