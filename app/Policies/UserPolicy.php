<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\User;

/**
 * Staff accounts are managed by Administrators and Owners only.
 * Accounts are deactivated rather than deleted, to keep the audit trail.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->managesStaff($user);
    }

    public function view(User $user, User $model): bool
    {
        return $this->managesStaff($user);
    }

    public function create(User $user): bool
    {
        return $this->managesStaff($user);
    }

    public function update(User $user, User $model): bool
    {
        if (! $this->managesStaff($user)) {
            return false;
        }

        // Only an Owner may edit another Owner's account.
        return ! $model->hasRole(StaffRole::Owner->value) || $user->hasRole(StaffRole::Owner->value);
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    private function managesStaff(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole([
            StaffRole::Administrator->value,
            StaffRole::Owner->value,
        ]);
    }
}
