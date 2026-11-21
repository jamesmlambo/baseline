<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
            self::Skipped => 'Not sent',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'gray',
            self::Sent => 'info',
            self::Delivered => 'success',
            self::Failed => 'danger',
            self::Skipped => 'warning',
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
