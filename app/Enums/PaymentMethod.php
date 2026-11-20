<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Setting;

/**
 * Payment methods (DEC-05). Display order is a setting (PEND-03); Instant EFT is always first.
 */
enum PaymentMethod: string
{
    case InstantEft = 'instant_eft';
    case Card = 'card';
    case Eft = 'eft';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::InstantEft => 'Instant EFT',
            self::Card => 'Card',
            self::Eft => 'EFT (bank transfer)',
            self::Cash => 'Cash',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::InstantEft => 'Pay straight from your bank account. Confirmed immediately.',
            self::Card => 'Pay by credit or debit card on a secure payment page.',
            self::Eft => 'Transfer from your bank. Your booking is confirmed once the money reaches our account.',
            self::Cash => 'At reception only.',
        };
    }

    /**
     * Paid through an online payment gateway (as opposed to recorded by staff).
     */
    public function isOnline(): bool
    {
        return in_array($this, [self::InstantEft, self::Card], true);
    }

    /**
     * Methods offered to guests on the website, in the configured order with Instant EFT first.
     *
     * @return list<self>
     */
    public static function onlineOrder(): array
    {
        $configured = Setting::get('payments.method_order', ['instant_eft', 'card', 'eft']);
        $methods = [];

        foreach ((array) $configured as $value) {
            $method = self::tryFrom((string) $value);

            if ($method !== null && $method !== self::Cash && ! in_array($method, $methods, true)) {
                $methods[] = $method;
            }
        }

        $methods = array_values(array_filter($methods, fn (self $method): bool => $method !== self::InstantEft));
        array_unshift($methods, self::InstantEft);

        foreach ([self::Card, self::Eft] as $required) {
            if (! in_array($required, $methods, true)) {
                $methods[] = $required;
            }
        }

        return $methods;
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
