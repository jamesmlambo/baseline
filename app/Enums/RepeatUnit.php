<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * How often a recurring task comes back: every N days, weeks or months (HK-08).
 */
enum RepeatUnit: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public function label(int $every = 1): string
    {
        $word = match ($this) {
            self::Day => 'day',
            self::Week => 'week',
            self::Month => 'month',
        };

        return $every === 1 ? "Every {$word}" : "Every {$every} {$word}s";
    }

    /**
     * The occurrence that is `$steps` repeats after the first. Months never overflow:
     * a task first due on 31 January comes back on 28/29 February, then 31 March.
     */
    public function occurrence(CarbonImmutable $first, int $steps): CarbonImmutable
    {
        return match ($this) {
            self::Day => $first->addDays($steps),
            self::Week => $first->addWeeks($steps),
            self::Month => $first->addMonthsNoOverflow($steps),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [self::Day->value => 'Days', self::Week->value => 'Weeks', self::Month->value => 'Months'];
    }
}
