<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Guest;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * Guest profiles hold personal data: front desk and management only. Never deleted from the UI
 * (retention rules come with the compliance work, PEND-11).
 */
class GuestPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->frontDesk($user);
    }

    public function view(User $user, Guest $guest): bool
    {
        return $this->frontDesk($user);
    }

    public function create(User $user): bool
    {
        return $this->frontDesk($user);
    }

    public function update(User $user, Guest $guest): bool
    {
        return $this->frontDesk($user);
    }

    public function delete(User $user, Guest $guest): bool
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
