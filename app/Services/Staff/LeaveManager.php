<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\StaffRole;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\StaffAlert;
use App\Services\Staff\Attendance\AttendanceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Leave requests (EMP-02/03). Staff ask for their own leave; the owner or a manager decides.
 * Managers can't approve their own leave. No balances or pay are calculated (DEC-09).
 */
class LeaveManager
{
    public function __construct(private readonly AttendanceCalculator $calculator) {}

    public function request(Employee $employee, LeaveType $type, CarbonImmutable $from, CarbonImmutable $until, ?string $reason, User $by): LeaveRequest
    {
        $isSelf = $employee->user_id !== null && $employee->user_id === $by->id;

        if (! $isSelf && ! $this->managesPeople($by)) {
            throw new AuthorizationException('You can only ask for your own leave.');
        }

        if ($until->lessThan($from)) {
            throw new InvalidArgumentException('The last day must be on or after the first day.');
        }

        $overlaps = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveStatus::Pending, LeaveStatus::Approved])
            ->whereDate('starts_on', '<=', $until->toDateString())
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->exists();

        if ($overlaps) {
            throw new InvalidArgumentException('There is already leave asked for or approved on some of these days.');
        }

        $leave = LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'type' => $type,
            'starts_on' => $from->toDateString(),
            'ends_on' => $until->toDateString(),
            'status' => LeaveStatus::Pending,
            'reason' => filled($reason) ? trim((string) $reason) : null,
            'requested_by' => $by->id,
        ]);

        if ($isSelf) {
            Notification::send(
                User::query()->role([StaffRole::Owner->value, StaffRole::Manager->value])->where('is_active', true)->whereKeyNot($by->id)->get(),
                new StaffAlert("Leave request: {$employee->displayName()}", "{$type->label()}, {$leave->starts_on->format('j M')} – {$leave->ends_on->format('j M Y')} ({$leave->days()} days).", url('/admin/leave-requests')),
            );
        }

        return $leave;
    }

    public function decide(LeaveRequest $leave, bool $approve, ?string $note, User $by): LeaveRequest
    {
        if (! $this->managesPeople($by)) {
            throw new AuthorizationException('Only the owner or a manager can decide leave.');
        }

        if ($leave->employee->user_id === $by->id && ! $by->hasRole(StaffRole::Owner->value)) {
            throw new AuthorizationException('You cannot approve your own leave. Ask the owner.');
        }

        if ($leave->status !== LeaveStatus::Pending) {
            throw new InvalidArgumentException('This request has already been decided.');
        }

        if (! $approve && blank($note)) {
            throw new InvalidArgumentException('Say why the leave is declined.');
        }

        $leave->forceFill([
            'status' => $approve ? LeaveStatus::Approved : LeaveStatus::Declined,
            'decision_note' => filled($note) ? trim((string) $note) : null,
            'decided_by' => $by->id,
            'decided_at' => now(),
        ])->save();

        $this->recalculatePastDays($leave);

        $leave->employee->user?->notify(new StaffAlert(
            'Leave '.($approve ? 'approved' : 'declined'),
            "{$leave->type->label()}, {$leave->starts_on->format('j M')} – {$leave->ends_on->format('j M Y')}".(filled($note) ? ": {$note}" : '.'),
            url('/staff/my-leave'),
        ));

        return $leave;
    }

    public function cancel(LeaveRequest $leave, User $by): LeaveRequest
    {
        $isSelf = $leave->employee->user_id === $by->id;

        if (! $isSelf && ! $this->managesPeople($by)) {
            throw new AuthorizationException('You cannot cancel this leave.');
        }

        if (! in_array($leave->status, [LeaveStatus::Pending, LeaveStatus::Approved], true)) {
            throw new InvalidArgumentException('This leave is already closed.');
        }

        if ($isSelf && ! $this->managesPeople($by) && $leave->status === LeaveStatus::Approved && $leave->starts_on->isPast()) {
            throw new InvalidArgumentException('Leave that has started can only be cancelled by a manager.');
        }

        $leave->forceFill(['status' => LeaveStatus::Cancelled])->save();
        $this->recalculatePastDays($leave);

        return $leave;
    }

    private function recalculatePastDays(LeaveRequest $leave): void
    {
        $day = CarbonImmutable::parse($leave->starts_on->toDateString(), 'Africa/Johannesburg');
        $last = CarbonImmutable::parse($leave->ends_on->toDateString(), 'Africa/Johannesburg');
        $today = CarbonImmutable::today('Africa/Johannesburg');

        while ($day->lessThanOrEqualTo($last) && $day->lessThanOrEqualTo($today)) {
            $this->calculator->derive($leave->employee, $day);
            $day = $day->addDay();
        }
    }

    private function managesPeople(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole([StaffRole::Owner->value, StaffRole::Manager->value]);
    }
}
