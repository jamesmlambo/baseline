<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the requested room or accommodation type has no free inventory for the stay.
 * The message is safe to show to staff and guests.
 */
class RoomUnavailableException extends RuntimeException
{
    public static function forType(string $typeName): self
    {
        return new self("Sorry, {$typeName} is no longer available for those dates.");
    }

    public static function forRoom(string $roomName): self
    {
        return new self("{$roomName} is not available for those dates.");
    }
}
