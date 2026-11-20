<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable South African rand amount stored as integer cents. Never use floats for money.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    public function __construct(public int $cents) {}

    public static function cents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Parse a rand amount typed by a person, e.g. "1 234.50" or "1234,5".
     */
    public static function fromRands(string $amount): self
    {
        $normalised = str_replace([' ', "\u{00A0}", 'R'], '', trim($amount));
        $normalised = str_replace(',', '.', $normalised);

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $normalised)) {
            throw new InvalidArgumentException("Invalid rand amount [{$amount}].");
        }

        [$rands, $fraction] = array_pad(explode('.', $normalised), 2, '0');
        $sign = str_starts_with($rands, '-') ? -1 : 1;

        return new self($sign * (abs((int) $rands) * 100 + (int) str_pad($fraction, 2, '0')));
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function multiply(int $times): self
    {
        return new self($this->cents * $times);
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    /**
     * Format as "R 1 234.50" (en_ZA display rule).
     */
    public function format(): string
    {
        $sign = $this->cents < 0 ? '-' : '';
        $absolute = abs($this->cents);
        $rands = intdiv($absolute, 100);
        $cents = $absolute % 100;

        return sprintf('%sR %s.%02d', $sign, number_format($rands, 0, '.', ' '), $cents);
    }

    public function __toString(): string
    {
        return $this->format();
    }

    public function jsonSerialize(): int
    {
        return $this->cents;
    }
}
