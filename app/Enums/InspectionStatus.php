<?php

declare(strict_types=1);

namespace App\Enums;

enum InspectionStatus: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'To inspect',
            self::Passed => 'Passed',
            self::Failed => 'Failed — rework',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Passed => 'success',
            self::Failed => 'danger',
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
