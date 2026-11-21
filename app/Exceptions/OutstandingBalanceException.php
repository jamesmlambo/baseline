<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\Money;
use RuntimeException;

/**
 * Checkout is blocked while money is owed (CHK-02), unless an authorised override is given.
 */
class OutstandingBalanceException extends RuntimeException
{
    public static function for(Money $balance): self
    {
        return new self("The guest still owes {$balance->format()}. Take payment first, or ask the owner to approve an override.");
    }
}
