<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RoomBlock;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * Blocks are released, never edited or deleted, so the inventory history is preserved.
 */
class RoomBlockPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->viewsInventory($user);
    }

    public function view(User $user, RoomBlock $block): bool
    {
        return $this->viewsInventory($user);
    }

    public function create(User $user): bool
    {
        return $this->managesInventory($user);
    }

    public function update(User $user, RoomBlock $block): bool
    {
        return false;
    }

    public function release(User $user, RoomBlock $block): bool
    {
        return $block->isActive() && $this->managesInventory($user);
    }

    public function delete(User $user, RoomBlock $block): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
