<?php

declare(strict_types=1);

namespace App\Enums;

enum GuestRequestStatus: string
{
    case New = 'new';
    case Acknowledged = 'acknowledged';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Sent',
            self::Acknowledged => 'On its way',
            self::Done => 'Done',
            self::Cancelled => 'Cancelled',
        };
    }

    public function staffLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Acknowledged => 'Acknowledged',
            self::Done => 'Done',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'danger',
            self::Acknowledged => 'warning',
            self::Done => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Acknowledged], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->staffLabel();
        }

        return $options;
    }
}
