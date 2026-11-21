<?php

declare(strict_types=1);

namespace App\Services\Staff\Attendance;

/**
 * A row a source could not read, reported back to the person importing.
 */
final readonly class SourceError
{
    public function __construct(public int $row, public string $message) {}
}
