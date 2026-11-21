<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffRole;
use App\Enums\TaskType;
use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * HK-03: reception assigns housekeeping; the owner or a manager assigns gardening and maintenance.
 * Workers see and work only on their own tasks.
 */
class TaskPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->isOperationsStaff($user);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->isFrontDesk($user)
            || $task->assigned_to === $user->id
            || $this->hasRole($user, StaffRole::Inspector);
    }

    public function create(User $user): bool
    {
        return $this->isFrontDesk($user);
    }

    /**
     * May this user create or assign tasks of this type?
     */
    public function assignType(User $user, TaskType $type): bool
    {
        return $type->isHousekeeping() ? $this->isFrontDesk($user) : $this->managesInventory($user);
    }

    public function assign(User $user, Task $task): bool
    {
        return $task->status->isOpen() && $this->assignType($user, $task->type);
    }

    public function cancel(User $user, Task $task): bool
    {
        return $this->assign($user, $task);
    }

    /**
     * Start, tick the checklist and complete: only the person it is assigned to.
     */
    public function work(User $user, Task $task): bool
    {
        return $user->is_active && $task->assigned_to === $user->id && $task->status->isOpen();
    }

    /**
     * Any operations staff member can report a fault (HK-08).
     */
    public function reportFault(User $user): bool
    {
        return $this->isOperationsStaff($user);
    }

    public function update(User $user, Task $task): bool
    {
        return false;
    }

    public function delete(User $user, Task $task): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
