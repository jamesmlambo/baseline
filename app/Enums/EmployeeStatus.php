<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Pending until a manager approves the profile (EMP-01).
 */
enum EmployeeStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for approval',
            self::Active => 'Active',
            self::Inactive => 'Left / inactive',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Inactive => 'gray',
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
