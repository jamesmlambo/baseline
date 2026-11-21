<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Inspection;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * HK-05: inspectors (or the owner/a manager) score inspections. Nobody inspects their own clean.
 */
class InspectionPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->isFrontDesk($user) || $this->hasRole($user, StaffRole::Inspector);
    }

    public function view(User $user, Inspection $inspection): bool
    {
        return $this->viewAny($user);
    }

    public function inspect(User $user, Inspection $inspection): bool
    {
        $inspection->loadMissing('task');

        return $this->hasRole($user, StaffRole::Inspector, StaffRole::Manager, StaffRole::Owner)
            && $inspection->task?->completed_by !== $user->id
            && ($inspection->inspector_id === null || $inspection->inspector_id === $user->id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Inspection $inspection): bool
    {
        return false;
    }

    public function delete(User $user, Inspection $inspection): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
