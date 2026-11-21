<?php

declare(strict_types=1);

namespace App\Services\Staff\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Enums\PunchDirection;
use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Services\Staff\Roster;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Derives attendance from the roster and raw punches (ATT-03):
 *
 * - Not rostered (day off, leave, holiday): Off duty — never Absent (EMP-04). Punches on such a
 *   day make it Pending review instead.
 * - Rostered: only punches inside the shift's clock-in/out window count. None → Absent; an in
 *   without an out (or the reverse) → Incomplete; first in after start + grace → Late; else Worked.
 * - A rostered day is only decided once its window has closed.
 * - Days corrected by a manager are never recalculated (ATT-04).
 */
class AttendanceCalculator
{
    public function __construct(private readonly Roster $roster) {}

    public function derive(Employee $employee, CarbonImmutable $date, ?CarbonImmutable $now = null): ?AttendanceDay
    {
        $now ??= CarbonImmutable::now();
        $date = CarbonImmutable::parse($date->toDateString(), 'Africa/Johannesburg');

        $existing = AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', $date->toDateString())->first();

        if ($existing?->is_corrected) {
            return $existing;
        }

        $rosterDay = $this->roster->dayFor($employee, $date);

        if ($rosterDay->isWorking()) {
            $shift = $rosterDay->shift;
            $period = $shift->periodOn($date);
            $window = $shift->windowOn($date);

            if ($now->lessThan($window['closes'])) {
                return $existing;
            }

            $punches = $this->punches($employee, $window['opens'], $window['closes']);
            [$firstIn, $lastOut] = $this->pair($punches);

            $status = match (true) {
                $punches->isEmpty() => AttendanceStatus::Absent,
                $firstIn === null || $lastOut === null => AttendanceStatus::Incomplete,
                $firstIn->greaterThan($period['start']->addMinutes($shift->late_after_minutes)) => AttendanceStatus::Late,
                default => AttendanceStatus::Worked,
            };

            return $this->save($employee, $date, [
                'shift_id' => $shift->id,
                'scheduled_start' => $period['start'],
                'scheduled_end' => $period['end'],
                'first_in' => $firstIn,
                'last_out' => $lastOut,
                'status' => $status,
                'minutes_worked' => $firstIn !== null && $lastOut !== null ? (int) $firstIn->diffInMinutes($lastOut) : 0,
                'late_minutes' => $firstIn !== null && $firstIn->greaterThan($period['start']) ? (int) $period['start']->diffInMinutes($firstIn) : 0,
            ]);
        }

        if ($employee->status === EmployeeStatus::Pending) {
            return $existing;
        }

        // Off duty: look at punches on this calendar day that don't belong to yesterday's night shift.
        $dayStart = $date->startOfDay();
        $dayEnd = $date->endOfDay();
        $yesterday = $this->roster->dayFor($employee, $date->subDay());

        if ($yesterday->isWorking()) {
            $dayStart = max($dayStart, $yesterday->shift->windowOn($date->subDay())['closes']);
        }

        $punches = $this->punches($employee, $dayStart, $dayEnd);
        [$firstIn, $lastOut] = $this->pair($punches);

        return $this->save($employee, $date, [
            'shift_id' => null,
            'scheduled_start' => null,
            'scheduled_end' => null,
            'first_in' => $punches->isEmpty() ? null : ($firstIn ?? $punches->first()->punched_at),
            'last_out' => $lastOut,
            'status' => $punches->isEmpty() ? AttendanceStatus::OffDuty : AttendanceStatus::PendingReview,
            'minutes_worked' => $firstIn !== null && $lastOut !== null ? (int) $firstIn->diffInMinutes($lastOut) : 0,
            'late_minutes' => 0,
        ]);
    }

    /**
     * Recalculate every active employee for a date. Returns the number of days saved.
     */
    public function deriveDate(CarbonImmutable $date, ?CarbonImmutable $now = null): int
    {
        $saved = 0;

        Employee::query()->where('status', EmployeeStatus::Active)->each(function (Employee $employee) use ($date, $now, &$saved): void {
            $saved += $this->derive($employee, $date, $now) !== null ? 1 : 0;
        });

        return $saved;
    }

    /**
     * @return Collection<int, AttendancePunch>
     */
    private function punches(Employee $employee, CarbonImmutable $from, CarbonImmutable $until): Collection
    {
        return AttendancePunch::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('punched_at', [$from, $until])
            ->orderBy('punched_at')
            ->get();
    }

    /**
     * First "in" (or unknown) punch and the last "out" (or unknown) punch after it.
     *
     * @param  Collection<int, AttendancePunch>  $punches
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function pair(Collection $punches): array
    {
        $first = $punches->first(fn (AttendancePunch $punch): bool => $punch->direction !== PunchDirection::Out);

        if ($first === null) {
            return [null, null];
        }

        $last = $punches->reverse()->first(fn (AttendancePunch $punch): bool => $punch->id !== $first->id
            && $punch->direction !== PunchDirection::In
            && $punch->punched_at->greaterThan($first->punched_at));

        return [$first->punched_at, $last?->punched_at];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function save(Employee $employee, CarbonImmutable $date, array $values): AttendanceDay
    {
        return AttendanceDay::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'work_date' => $date->toDateString()],
            $values,
        );
    }
}
