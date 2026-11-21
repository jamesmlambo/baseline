<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

class MenuItemPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->viewsInventory($user);
    }

    public function view(User $user, MenuItem $item): bool
    {
        return $this->viewsInventory($user);
    }

    public function create(User $user): bool
    {
        return $this->managesInventory($user);
    }

    /**
     * Reception may mark items available or sold out; managers edit everything.
     */
    public function update(User $user, MenuItem $item): bool
    {
        return $this->viewsInventory($user);
    }

    public function delete(User $user, MenuItem $item): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
