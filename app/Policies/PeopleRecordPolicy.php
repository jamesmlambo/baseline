<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PublicHoliday;
use App\Models\RosterChange;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;
use Illuminate\Database\Eloquent\Model;

/**
 * EMP-03: HR records (employees, departments, shifts, rosters, leave, attendance) are for the
 * owner and managers. Staff reach their own records only through the /staff screens, which are
 * scoped to them. Nothing here is deleted except one-off roster changes and holiday dates.
 */
class PeopleRecordPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->managesPeople($user);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->managesPeople($user);
    }

    public function create(User $user): bool
    {
        return $this->managesPeople($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->managesPeople($user);
    }

    public function delete(User $user, Model $record): bool
    {
        return ($record instanceof RosterChange || $record instanceof PublicHoliday) && $this->managesPeople($user);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
