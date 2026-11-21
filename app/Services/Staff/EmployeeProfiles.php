<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\EmployeeStatus;
use App\Enums\StaffRole;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\StaffAlert;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * EMP-01: staff complete their own profile (contact details, emergency contact, photo); the
 * owner or a manager approves before it takes effect.
 */
class EmployeeProfiles
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function submit(Employee $employee, array $changes, ?string $photoPath, User $by): Employee
    {
        if ($employee->user_id === null || $employee->user_id !== $by->id) {
            throw new AuthorizationException('You can only change your own profile.');
        }

        $changes = array_map(
            fn (mixed $value): ?string => filled($value) ? trim((string) $value) : null,
            array_intersect_key($changes, array_flip(Employee::SELF_SERVICE_FIELDS)),
        );
        $changes = array_filter($changes, fn (?string $value, string $field): bool => $value !== $employee->{$field}, ARRAY_FILTER_USE_BOTH);

        if ($changes === [] && $photoPath === null) {
            throw new InvalidArgumentException('Nothing has changed.');
        }

        if ($photoPath !== null) {
            $employee->addMedia($photoPath)->toMediaCollection('pending_photo', 'local');
            $changes['photo'] = 'new';
        }

        $employee->forceFill([
            'pending_changes' => array_merge($employee->pending_changes ?? [], $changes),
            'changes_submitted_at' => now(),
        ])->save();

        Notification::send(
            User::query()->role([StaffRole::Owner->value, StaffRole::Manager->value])->where('is_active', true)->get(),
            new StaffAlert("Profile update to approve: {$employee->displayName()}", 'A staff member updated their details.', url('/admin/employees')),
        );

        return $employee;
    }

    /**
     * Approve a new employee and/or their pending changes.
     */
    public function approve(Employee $employee, User $by): Employee
    {
        $this->authorize($by, $employee);

        $changes = $employee->pending_changes ?? [];
        $fields = array_intersect_key($changes, array_flip(Employee::SELF_SERVICE_FIELDS));

        if (array_key_exists('photo', $changes)) {
            $employee->getFirstMedia('pending_photo')?->move($employee, 'photo', 'local');
        }

        $employee->forceFill($fields + [
            'pending_changes' => null,
            'changes_submitted_at' => null,
            'status' => $employee->status === EmployeeStatus::Pending ? EmployeeStatus::Active : $employee->status,
            'approved_by' => $by->id,
            'approved_at' => now(),
        ])->save();

        activity('people')->performedOn($employee)->causedBy($by)->event('profile_approved')
            ->withProperties(['fields' => array_keys($changes)])->log("Profile approved for {$employee->full_name}");

        return $employee;
    }

    public function reject(Employee $employee, string $reason, User $by): Employee
    {
        $this->authorize($by, $employee);

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Say why the changes are not accepted.');
        }

        $employee->clearMediaCollection('pending_photo');
        $employee->forceFill(['pending_changes' => null, 'changes_submitted_at' => null])->save();

        $employee->user?->notify(new StaffAlert('Profile changes not accepted', trim($reason), url('/staff/my-profile')));

        return $employee;
    }

    private function authorize(User $by, Employee $employee): void
    {
        if (! $by->is_active || ! $by->hasAnyRole([StaffRole::Owner->value, StaffRole::Manager->value])) {
            throw new AuthorizationException('Only the owner or a manager can approve profiles.');
        }

        if ($employee->user_id === $by->id && ! $by->hasRole(StaffRole::Owner->value)) {
            throw new AuthorizationException('You cannot approve your own profile.');
        }
    }
}
