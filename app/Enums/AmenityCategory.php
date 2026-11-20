<?php

declare(strict_types=1);

namespace App\Enums;

enum AmenityCategory: string
{
    case Room = 'room';
    case Property = 'property';

    public function label(): string
    {
        return match ($this) {
            self::Room => 'In the room',
            self::Property => 'At the lodge',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [self::Room->value => self::Room->label(), self::Property->value => self::Property->label()];
    }
}
