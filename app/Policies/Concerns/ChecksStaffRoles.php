<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Enums\StaffRole;
use App\Models\User;

trait ChecksStaffRoles
{
    protected function hasRole(User $user, StaffRole ...$roles): bool
    {
        return $user->is_active && $user->hasAnyRole(array_map(
            fn (StaffRole $role): string => $role->value,
            $roles,
        ));
    }

    /**
     * Owner, Administrator and Manager manage rooms, rates and blocks.
     */
    protected function managesInventory(User $user): bool
    {
        return $this->hasRole($user, StaffRole::Administrator, StaffRole::Owner, StaffRole::Manager);
    }

    /**
     * Reception can see inventory but not change it.
     */
    protected function viewsInventory(User $user): bool
    {
        return $this->managesInventory($user) || $this->hasRole($user, StaffRole::Receptionist);
    }
}
