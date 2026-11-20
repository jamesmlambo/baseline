<?php

declare(strict_types=1);

namespace App\Enums;

enum PostType: string
{
    case News = 'news';
    case Event = 'event';
    case Announcement = 'announcement';
    case Promotion = 'promotion';

    public function label(): string
    {
        return match ($this) {
            self::News => 'News',
            self::Event => 'Event',
            self::Announcement => 'Announcement',
            self::Promotion => 'Promotion',
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
