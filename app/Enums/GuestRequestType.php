<?php

declare(strict_types=1);

namespace App\Enums;

enum GuestRequestType: string
{
    case Towels = 'towels';
    case Toiletries = 'toiletries';
    case Assistance = 'assistance';
    case Meal = 'meal';
    case Checkout = 'checkout';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Towels => 'Towels',
            self::Toiletries => 'Toiletries',
            self::Assistance => 'Help needed',
            self::Meal => 'Meal order',
            self::Checkout => 'Checkout',
            self::Other => 'Other',
        };
    }

    /**
     * Types a guest can pick on the simple request form.
     *
     * @return array<string, string>
     */
    public static function simpleOptions(): array
    {
        return collect([self::Towels, self::Toiletries, self::Assistance, self::Other])
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
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
