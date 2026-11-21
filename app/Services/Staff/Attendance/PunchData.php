<?php

declare(strict_types=1);

namespace App\Services\Staff\Attendance;

use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use Carbon\CarbonImmutable;

/**
 * One clock event as read from a source, before it is stored.
 */
final readonly class PunchData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public PunchSource $source,
        public string $devicePersonId,
        public CarbonImmutable $punchedAt,
        public PunchDirection $direction = PunchDirection::Unknown,
        public ?string $deviceSerial = null,
        public ?string $verifyMode = null,
        public array $raw = [],
    ) {}
}
