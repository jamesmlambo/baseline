<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Extra;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

class ExtraPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->viewsInventory($user);
    }

    public function view(User $user, Extra $extra): bool
    {
        return $this->viewsInventory($user);
    }

    public function create(User $user): bool
    {
        return $this->managesInventory($user);
    }

    public function update(User $user, Extra $extra): bool
    {
        return $this->managesInventory($user);
    }

    public function delete(User $user, Extra $extra): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
