<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RefundStatus;
use App\Enums\StaffRole;
use App\Models\Refund;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

class RefundPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->hasRole($user, StaffRole::Administrator, StaffRole::Owner, StaffRole::Manager, StaffRole::Receptionist);
    }

    public function view(User $user, Refund $refund): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Refund $refund): bool
    {
        return false;
    }

    /**
     * DEC-06: finance roles only, and never the person who asked for it.
     */
    public function decide(User $user, Refund $refund): bool
    {
        return $refund->status === RefundStatus::Requested
            && $refund->requested_by !== $user->id
            && $this->hasRole($user, StaffRole::Administrator, StaffRole::Owner, StaffRole::Manager);
    }

    public function delete(User $user, Refund $refund): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
