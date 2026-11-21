<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a service needs one of, per booking (LSR-03).
 */
enum ServiceResourceType: string
{
    case Therapist = 'therapist';
    case TreatmentRoom = 'treatment_room';
    case Area = 'area';

    public function label(): string
    {
        return match ($this) {
            self::Therapist => 'Therapist',
            self::TreatmentRoom => 'Treatment room',
            self::Area => 'Area / venue',
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
