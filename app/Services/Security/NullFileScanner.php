<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * No scanner connected: every file is reported as not scanned, never as clean.
 */
class NullFileScanner implements FileScanner
{
    public function scan(string $path): string
    {
        return self::NOT_SCANNED;
    }
}
