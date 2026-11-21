<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;
use Illuminate\Database\Eloquent\Model;

/**
 * Guest requests and guest messages: front desk and management read and answer them.
 * They are never created by staff or deleted (they are part of the stay record).
 */
class FrontDeskRecordPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->frontDesk($user);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->frontDesk($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $model): bool
    {
        return $this->frontDesk($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function frontDesk(User $user): bool
    {
        return $this->hasRole($user, StaffRole::Administrator, StaffRole::Owner, StaffRole::Manager, StaffRole::Receptionist);
    }
}
