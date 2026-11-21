<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LostPropertyItem;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * HK-10: restricted to the front desk, the owner and managers. Entries are never deleted.
 */
class LostPropertyItemPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->isFrontDesk($user);
    }

    public function view(User $user, LostPropertyItem $item): bool
    {
        return $this->isFrontDesk($user);
    }

    public function create(User $user): bool
    {
        return $this->isFrontDesk($user);
    }

    public function update(User $user, LostPropertyItem $item): bool
    {
        return $this->isFrontDesk($user);
    }

    public function delete(User $user, LostPropertyItem $item): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
