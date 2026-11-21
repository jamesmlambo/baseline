<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Shift;
use Carbon\CarbonImmutable;

/**
 * What the roster says for one employee on one date.
 */
final readonly class RosterDay
{
    public const WORKING = 'working';

    public const OFF = 'off';

    public const LEAVE = 'leave';

    public const HOLIDAY = 'holiday';

    public function __construct(
        public CarbonImmutable $date,
        public string $kind,
        public ?Shift $shift = null,
        public ?LeaveRequest $leave = null,
        public ?Employee $coveringFor = null,
        public ?string $note = null,
    ) {}

    public function isWorking(): bool
    {
        return $this->kind === self::WORKING && $this->shift !== null;
    }

    public function label(): string
    {
        return match ($this->kind) {
            self::WORKING => $this->shift->name.' '.$this->shift->timesLabel().($this->coveringFor ? " (covering for {$this->coveringFor->displayName()})" : ''),
            self::LEAVE => $this->leave?->type->label() ?? 'Leave',
            self::HOLIDAY => 'Public holiday — off',
            default => 'Off',
        };
    }
}
