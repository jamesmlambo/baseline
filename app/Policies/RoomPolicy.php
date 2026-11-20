<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Room;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * Rooms are deactivated, never deleted, so booking history stays intact.
 */
class RoomPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->viewsInventory($user);
    }

    public function view(User $user, Room $room): bool
    {
        return $this->viewsInventory($user);
    }

    public function create(User $user): bool
    {
        return $this->managesInventory($user);
    }

    public function update(User $user, Room $room): bool
    {
        return $this->managesInventory($user);
    }

    public function delete(User $user, Room $room): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
