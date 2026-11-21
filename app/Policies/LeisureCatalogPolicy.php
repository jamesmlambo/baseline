<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;
use Illuminate\Database\Eloquent\Model;

/**
 * Services, therapists/rooms and meal ordering hours: reception can look, the owner or a
 * manager sets them up. Switched off rather than deleted, so past bookings keep their history.
 */
class LeisureCatalogPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->isFrontDesk($user);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->isFrontDesk($user);
    }

    public function create(User $user): bool
    {
        return $this->managesInventory($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->managesInventory($user);
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
