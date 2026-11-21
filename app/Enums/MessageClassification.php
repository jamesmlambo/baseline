<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * MSG-02/04: operational messages are part of a stay; marketing needs prior consent.
 */
enum MessageClassification: string
{
    case Operational = 'operational';
    case Marketing = 'marketing';

    public function label(): string
    {
        return match ($this) {
            self::Operational => 'Operational',
            self::Marketing => 'Marketing',
        };
    }
}
