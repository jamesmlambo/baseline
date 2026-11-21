<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Derived attendance statuses (ATT-03). Off-duty staff are never Absent (EMP-04).
 */
enum AttendanceStatus: string
{
    case Worked = 'worked';
    case Late = 'late';
    case Absent = 'absent';
    case Incomplete = 'incomplete';
    case OffDuty = 'off_duty';
    case PendingReview = 'pending_review';

    public function label(): string
    {
        return match ($this) {
            self::Worked => 'Worked',
            self::Late => 'Late',
            self::Absent => 'Absent',
            self::Incomplete => 'Incomplete',
            self::OffDuty => 'Off duty',
            self::PendingReview => 'Pending review',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Worked => 'success',
            self::Late => 'warning',
            self::Absent => 'danger',
            self::Incomplete => 'warning',
            self::OffDuty => 'gray',
            self::PendingReview => 'info',
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
