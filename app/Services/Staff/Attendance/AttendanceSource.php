<?php

declare(strict_types=1);

namespace App\Services\Staff\Attendance;

use App\Enums\PunchSource;
use Carbon\CarbonImmutable;

/**
 * ATT-01: where punches come from. Implementations: CSV file, Hikvision terminal (pending the
 * on-device proof of concept, docs/HIKVISION_POC.md). Manual entry goes through PunchRecorder.
 */
interface AttendanceSource
{
    public function source(): PunchSource;

    /**
     * @return iterable<int, PunchData|SourceError>
     */
    public function read(CarbonImmutable $from, CarbonImmutable $until): iterable;
}
