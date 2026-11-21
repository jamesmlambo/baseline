<?php

declare(strict_types=1);

namespace App\Enums;

enum LostPropertyStatus: string
{
    case Held = 'held';
    case Returned = 'returned';
    case Donated = 'donated';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Held => 'In storage',
            self::Returned => 'Returned to owner',
            self::Donated => 'Donated',
            self::Discarded => 'Discarded',
        };
    }

    public function color(): string
    {
        return $this === self::Held ? 'warning' : 'gray';
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
