<?php

declare(strict_types=1);

namespace App\Services\Staff\Attendance;

use App\Enums\PunchSource;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * ATT-01: Hikvision DS-K1T342MFWX-E1 adapter. Not connected: no live integrations until the
 * on-device proof of concept passes (docs/HIKVISION_POC.md — ISAPI event search vs ISUP push,
 * firmware, network, duplicates, outage recovery, timezone). Until then use the CSV import.
 * Only punch events will ever be read; biometric templates stay on the device (DEC-07).
 */
class HikvisionAttendanceSource implements AttendanceSource
{
    public function source(): PunchSource
    {
        return PunchSource::Hikvision;
    }

    /**
     * @return iterable<int, PunchData|SourceError>
     */
    public function read(CarbonImmutable $from, CarbonImmutable $until): iterable
    {
        throw new RuntimeException('The Hikvision terminal is not connected yet. Import a CSV export instead (see docs/HIKVISION_POC.md).');
    }
}
