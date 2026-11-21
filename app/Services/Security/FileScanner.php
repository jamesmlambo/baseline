<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * SEC-08: virus scanning for uploads. Only a null scanner exists; the app must never present
 * uploads as scanned until a real scanner (e.g. ClamAV) is connected (docs/INTEGRATIONS.md).
 */
interface FileScanner
{
    public const CLEAN = 'clean';

    public const INFECTED = 'infected';

    public const NOT_SCANNED = 'not_scanned';

    /**
     * @return self::CLEAN|self::INFECTED|self::NOT_SCANNED
     */
    public function scan(string $path): string;
}
