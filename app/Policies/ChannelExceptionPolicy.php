<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ChannelException;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * The channel exception queue is handled by the front desk, managers and the owner.
 */
class ChannelExceptionPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->isFrontDesk($user);
    }

    public function view(User $user, ChannelException $exception): bool
    {
        return $this->isFrontDesk($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ChannelException $exception): bool
    {
        return $this->isFrontDesk($user) && $exception->status === 'open';
    }

    public function delete(User $user, ChannelException $exception): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
