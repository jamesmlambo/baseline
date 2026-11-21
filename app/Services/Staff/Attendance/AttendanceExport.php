<?php

declare(strict_types=1);

namespace App\Services\Staff\Attendance;

use App\Enums\StaffRole;
use App\Models\AttendanceDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * ATT-05: monthly payroll-ready CSV. Hours and statuses only — no pay is calculated (DEC-09).
 */
class AttendanceExport
{
    public const HEADER = [
        'Employee number', 'Name', 'Department', 'Date', 'Shift', 'Scheduled start', 'Scheduled end',
        'First in', 'Last out', 'Hours worked', 'Minutes worked', 'Late minutes', 'Status', 'Corrected', 'Correction reason',
    ];

    public function month(int $year, int $month, User $by): string
    {
        if (! $by->is_active || ! $by->hasAnyRole([StaffRole::Owner->value, StaffRole::Manager->value])) {
            throw new AuthorizationException('Only the owner or a manager can export attendance.');
        }

        $from = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Africa/Johannesburg');
        $until = $from->endOfMonth();

        $days = AttendanceDay::query()
            ->with(['employee.department', 'shift'])
            ->whereBetween('work_date', [$from->toDateString(), $until->toDateString()])
            ->join('employees', 'employees.id', '=', 'attendance_days.employee_id')
            ->orderBy('employees.full_name')
            ->orderBy('attendance_days.work_date')
            ->select('attendance_days.*')
            ->get();

        $handle = fopen('php://temp', 'r+b');
        fputcsv($handle, self::HEADER, escape: '\\');

        $time = fn (?CarbonImmutable $value): string => $value?->setTimezone('Africa/Johannesburg')->format('Y-m-d H:i') ?? '';

        foreach ($days as $day) {
            fputcsv($handle, array_map([$this, 'safe'], [
                $day->employee->employee_number ?? '',
                $day->employee->full_name,
                $day->employee->department?->name ?? '',
                $day->work_date->toDateString(),
                $day->shift?->name ?? '',
                $time($day->scheduled_start),
                $time($day->scheduled_end),
                $time($day->first_in),
                $time($day->last_out),
                $day->hoursLabel(),
                (string) $day->minutes_worked,
                (string) $day->late_minutes,
                $day->status->label(),
                $day->is_corrected ? 'Yes' : 'No',
                $day->correction_reason ?? '',
            ]), escape: '\\');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        activity('attendance')->causedBy($by)->event('attendance_exported')
            ->withProperties(['month' => $from->format('Y-m'), 'rows' => $days->count()])
            ->log("Attendance exported for {$from->format('F Y')}");

        return $csv;
    }

    /**
     * Stops spreadsheet formula injection from names or reasons typed by people.
     */
    private function safe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
