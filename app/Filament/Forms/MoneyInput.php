<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Support\Money;
use Filament\Forms\Components\TextInput;

/**
 * A rand amount field for integer-cents columns cast to Money. Staff type "950" or "1 234.50".
 */
final class MoneyInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->prefix('R')
            ->inputMode('decimal')
            ->rule('regex:/^\s*R?\s*\d{1,3}([ ,]?\d{3})*([.,]\d{1,2})?\s*$/')
            ->formatStateUsing(fn (mixed $state): ?string => match (true) {
                $state instanceof Money => number_format($state->cents / 100, 2, '.', ''),
                is_int($state) => number_format($state / 100, 2, '.', ''),
                default => $state,
            })
            ->dehydrateStateUsing(fn (?string $state): ?Money => filled($state) ? Money::fromRands(str_replace(',', '', (string) $state)) : null);
    }
}
