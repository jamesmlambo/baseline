<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Saves a staff member's roles (SEC-06). Only an Owner may give or take away the Owner role:
 * Owner unlocks financial overrides, refund approval and guest anonymisation, so an
 * Administrator must not be able to grant it to anyone — including themselves.
 */
class SyncStaffRoles
{
    /**
     * @param  list<string>  $roleNames
     *
     * @throws ValidationException
     */
    public function handle(User $target, array $roleNames, User $by, string $errorKey = 'role_names'): void
    {
        $valid = array_map(fn (StaffRole $role): string => $role->value, StaffRole::cases());
        $roleNames = array_values(array_unique(array_intersect($roleNames, $valid)));
        $owner = StaffRole::Owner->value;

        $hadOwner = $target->exists && $target->hasRole($owner);
        $getsOwner = in_array($owner, $roleNames, true);

        if ($hadOwner !== $getsOwner && ! $by->hasRole($owner)) {
            throw ValidationException::withMessages([
                $errorKey => 'Only an Owner can give or remove the Owner role.',
            ]);
        }

        $target->syncRoles($roleNames);
    }
}
