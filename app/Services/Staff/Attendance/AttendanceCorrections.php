<?php

declare(strict_types=1);

namespace App\Services\Staff\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\StaffRole;
use App\Models\AttendanceDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * ATT-04: manual corrections need permission and a reason, and are audited with before and after.
 * A corrected day is locked against automatic recalculation.
 */
class AttendanceCorrections
{
    public function correct(AttendanceDay $day, AttendanceStatus $status, ?CarbonImmutable $firstIn, ?CarbonImmutable $lastOut, string $reason, User $by): AttendanceDay
    {
        if (! $by->is_active || ! $by->hasAnyRole([StaffRole::Owner->value, StaffRole::Manager->value])) {
            throw new AuthorizationException('Only the owner or a manager can correct attendance.');
        }

        if ($day->employee?->user_id !== null && $day->employee->user_id === $by->id && ! $by->hasRole(StaffRole::Owner->value)) {
            throw new AuthorizationException('You cannot correct your own attendance. Ask the owner or another manager.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Give a reason for the correction.');
        }

        if ($firstIn !== null && $lastOut !== null && $lastOut->lessThanOrEqualTo($firstIn)) {
            throw new InvalidArgumentException('The clock-out time must be after the clock-in time.');
        }

        $before = $day->only(['status', 'first_in', 'last_out', 'minutes_worked']);

        $day->forceFill([
            'status' => $status,
            'first_in' => $firstIn,
            'last_out' => $lastOut,
            'minutes_worked' => $firstIn !== null && $lastOut !== null ? (int) $firstIn->diffInMinutes($lastOut) : 0,
            'late_minutes' => $status === AttendanceStatus::Late && $firstIn !== null && $day->scheduled_start !== null && $firstIn->greaterThan($day->scheduled_start)
                ? (int) $day->scheduled_start->diffInMinutes($firstIn)
                : 0,
            'is_corrected' => true,
            'correction_reason' => trim($reason),
            'corrected_by' => $by->id,
            'corrected_at' => now(),
        ])->save();

        activity('attendance')
            ->performedOn($day)
            ->causedBy($by)
            ->event('attendance_corrected')
            ->withProperties([
                'before' => array_map(fn (mixed $value): mixed => $value instanceof \BackedEnum ? $value->value : ($value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : $value), $before),
                'after' => ['status' => $status->value, 'first_in' => $firstIn?->toIso8601String(), 'last_out' => $lastOut?->toIso8601String(), 'minutes_worked' => $day->minutes_worked],
                'reason' => trim($reason),
            ])
            ->log('Attendance corrected');

        return $day;
    }
}
