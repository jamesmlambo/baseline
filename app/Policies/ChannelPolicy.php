<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Channel;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * Channels: reception can look; the owner, administrator or a manager sets them up. Channels
 * come from the approved list (DEC-03) and are switched off, never deleted.
 */
class ChannelPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->isFrontDesk($user);
    }

    public function view(User $user, Channel $channel): bool
    {
        return $this->isFrontDesk($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Channel $channel): bool
    {
        return $this->managesInventory($user);
    }

    public function delete(User $user, Channel $channel): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
