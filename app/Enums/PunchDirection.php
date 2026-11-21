<?php

declare(strict_types=1);

namespace App\Enums;

enum PunchDirection: string
{
    case In = 'in';
    case Out = 'out';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::In => 'In',
            self::Out => 'Out',
            self::Unknown => 'Unknown',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::In => 'success',
            self::Out => 'info',
            self::Unknown => 'gray',
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
