<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementReason: string
{
    case Received = 'received';
    case Used = 'used';
    case Damaged = 'damaged';
    case Adjusted = 'adjusted';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Used => 'Used',
            self::Damaged => 'Damaged / lost',
            self::Adjusted => 'Stock-take correction',
        };
    }

    /**
     * Received adds stock, used and damaged remove it, an adjustment can go either way.
     */
    public function sign(): int
    {
        return match ($this) {
            self::Received => 1,
            self::Used, self::Damaged => -1,
            self::Adjusted => 0,
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
