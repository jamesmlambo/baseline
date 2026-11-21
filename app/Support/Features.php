<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * Optional extras behind feature flags, all off until the client decides (PEND-14, LSR-06).
 * Flags are settings with the value "yes" or "no".
 */
final class Features
{
    public const INDEMNITY = 'features.indemnity';

    public const VOUCHERS = 'features.vouchers';

    public const PACKAGES = 'features.packages';

    public const EVENT_HIRE = 'features.event_hire';

    public static function enabled(string $flag): bool
    {
        return in_array(mb_strtolower(trim((string) Setting::get($flag, 'no'))), ['yes', 'true', '1', 'on'], true);
    }
}
