<?php

declare(strict_types=1);

use App\Enums\EmployeeStatus;
use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\RosterPattern;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;

function activeEmployee(?User $user = null, string $name = 'Thandi Demo', ?string $clockId = null): Employee
{
    static $counter = 0;
    $counter++;

    return Employee::query()->create([
        'user_id' => $user?->id,
        'full_name' => $name,
        'employee_number' => 'E'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT).bin2hex(random_bytes(2)),
        'device_person_id' => $clockId,
        'status' => EmployeeStatus::Active,
        'is_demo' => true,
    ]);
}

function dayShift(string $starts = '08:00', string $ends = '16:00', int $lateAfter = 5): Shift
{
    return Shift::query()->create([
        'name' => "Test shift {$starts}",
        'starts_at' => $starts,
        'ends_at' => $ends,
        'late_after_minutes' => $lateAfter,
        'clock_in_opens_minutes' => 60,
        'clock_out_closes_minutes' => 180,
        'is_demo' => true,
    ]);
}

/**
 * Rosters the employee on this shift on the given ISO weekdays (default every day).
 *
 * @param  list<int>  $weekdays
 */
function rosterOn(Employee $employee, Shift $shift, array $weekdays = [1, 2, 3, 4, 5, 6, 7], bool $worksPublicHolidays = true): void
{
    foreach ($weekdays as $weekday) {
        RosterPattern::query()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'weekday' => $weekday,
            'effective_from' => '2027-01-01',
            'works_public_holidays' => $worksPublicHolidays,
        ]);
    }
}

function punch(Employee $employee, string $localTime, PunchDirection $direction = PunchDirection::Unknown): AttendancePunch
{
    $at = CarbonImmutable::parse($localTime, 'Africa/Johannesburg');

    return AttendancePunch::query()->create([
        'source' => PunchSource::Csv,
        'device_person_id' => $employee->device_person_id ?? 'p'.$employee->id,
        'employee_id' => $employee->id,
        'punched_at' => $at,
        'direction' => $direction,
        'dedup_key' => AttendancePunch::dedupKey(PunchSource::Csv, null, 'p'.$employee->id, $at, $direction),
    ]);
}

function sast(string $value): CarbonImmutable
{
    return CarbonImmutable::parse($value, 'Africa/Johannesburg');
}

/**
 * Writes CSV text to a temporary file and returns its path.
 */
function csvFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'att').'.csv';
    file_put_contents($path, $contents);

    return $path;
}
