<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\EmployeeStatus;
use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use App\Models\RosterChange;
use App\Models\RosterPattern;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Who works when (EMP-02). Order of precedence for a date:
 * roster change (a different shift, a day off or cover) → approved leave → public holiday
 * (if the pattern says so) → the weekly pattern → off.
 */
class Roster
{
    public function dayFor(Employee $employee, CarbonImmutable $date): RosterDay
    {
        $date = CarbonImmutable::parse($date->toDateString(), 'Africa/Johannesburg');
        $day = $date->toDateString();

        if ($employee->status !== EmployeeStatus::Active
            || ($employee->starts_on !== null && $day < $employee->starts_on->toDateString())
            || ($employee->ends_on !== null && $day > $employee->ends_on->toDateString())) {
            return new RosterDay($date, RosterDay::OFF, note: 'Not employed on this date');
        }

        $change = RosterChange::query()->with(['shift', 'coveringFor'])->where('employee_id', $employee->id)->whereDate('work_date', $day)->first();

        if ($change !== null) {
            return $change->shift === null
                ? new RosterDay($date, RosterDay::OFF, note: $change->reason)
                : new RosterDay($date, RosterDay::WORKING, $change->shift, coveringFor: $change->coveringFor, note: $change->reason);
        }

        $leave = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatus::Approved)
            ->whereDate('starts_on', '<=', $day)
            ->whereDate('ends_on', '>=', $day)
            ->first();

        if ($leave !== null) {
            return new RosterDay($date, RosterDay::LEAVE, leave: $leave);
        }

        $pattern = RosterPattern::query()
            ->with('shift')
            ->where('employee_id', $employee->id)
            ->where('weekday', $date->isoWeekday())
            ->whereDate('effective_from', '<=', $day)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $day))
            ->latest('effective_from')
            ->first();

        if ($pattern === null || ! $pattern->shift->is_active) {
            return new RosterDay($date, RosterDay::OFF);
        }

        if (! $pattern->works_public_holidays && PublicHoliday::query()->whereDate('date', $day)->exists()) {
            return new RosterDay($date, RosterDay::HOLIDAY);
        }

        return new RosterDay($date, RosterDay::WORKING, $pattern->shift);
    }

    /**
     * @return list<RosterDay>
     */
    public function daysFor(Employee $employee, CarbonImmutable $from, int $days): array
    {
        return array_map(fn (int $offset): RosterDay => $this->dayFor($employee, $from->addDays($offset)), range(0, $days - 1));
    }

    /**
     * Is the employee inside a rostered shift at this moment? Checks yesterday too, for night shifts.
     */
    public function isOnDuty(Employee $employee, CarbonImmutable $at): bool
    {
        $local = $at->setTimezone('Africa/Johannesburg');

        foreach ([$local->subDay(), $local] as $date) {
            $day = $this->dayFor($employee, $date);

            if ($day->isWorking()) {
                $period = $day->shift->periodOn($day->date);

                if ($at->betweenIncluded($period['start'], $period['end'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * EMP-04: an active employee outside their shift. People without an employee profile are never treated as off duty.
     */
    public function isOffDuty(User $user, ?CarbonImmutable $at = null): bool
    {
        $employee = $user->employee()->first();

        if ($employee === null || $employee->status !== EmployeeStatus::Active) {
            return false;
        }

        return ! $this->isOnDuty($employee, $at ?? CarbonImmutable::now());
    }
}
