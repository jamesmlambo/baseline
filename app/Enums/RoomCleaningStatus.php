<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Room readiness (HK-06). A room is Ready only after its clean is done and the inspection passes,
 * or after an audited owner override (PEND-06).
 */
enum RoomCleaningStatus: string
{
    case Dirty = 'dirty';
    case Cleaning = 'cleaning';
    case AwaitingInspection = 'awaiting_inspection';
    case Ready = 'ready';

    public function label(): string
    {
        return match ($this) {
            self::Dirty => 'Dirty',
            self::Cleaning => 'Being cleaned',
            self::AwaitingInspection => 'Awaiting inspection',
            self::Ready => 'Ready',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Dirty => 'danger',
            self::Cleaning => 'info',
            self::AwaitingInspection => 'warning',
            self::Ready => 'success',
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
