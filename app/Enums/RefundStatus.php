<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Refund workflow (PAY-08, DEC-06): requested by one person, approved by a different one.
 */
enum RefundStatus: string
{
    case Requested = 'requested';
    case Processed = 'processed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Awaiting second approval',
            self::Processed => 'Refunded',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Processed => 'success',
            self::Rejected => 'gray',
        };
    }
}
