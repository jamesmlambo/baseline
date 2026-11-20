<?php

declare(strict_types=1);

use App\Support\Money;

it('formats rands the South African way', function (int $cents, string $expected): void {
    expect(Money::cents($cents)->format())->toBe($expected);
})->with([
    [123_450, 'R 1 234.50'],
    [0, 'R 0.00'],
    [5, 'R 0.05'],
    [100_000_000, 'R 1 000 000.00'],
    [-2_500, '-R 25.00'],
]);

it('parses typed rand amounts without floats', function (string $input, int $cents): void {
    expect(Money::fromRands($input)->cents)->toBe($cents);
})->with([
    ['1234.50', 123_450],
    ['1 234,5', 123_450],
    ['R 950', 95_000],
    ['0.1', 10],
    ['-12.34', -1_234],
]);

it('rejects amounts it cannot read', function (): void {
    Money::fromRands('12.345');
})->throws(InvalidArgumentException::class);

it('adds, subtracts and multiplies exactly', function (): void {
    $total = Money::cents(10)->add(Money::cents(20))->multiply(3)->subtract(Money::cents(1));

    expect($total->cents)->toBe(89);
});
