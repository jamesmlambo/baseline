<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Message;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * The message log is read-only; front desk may resend (MSG-05). Nothing is ever deleted.
 */
class MessagePolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->frontDesk($user);
    }

    public function view(User $user, Message $message): bool
    {
        return $this->frontDesk($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Message $message): bool
    {
        return false;
    }

    public function resend(User $user, Message $message): bool
    {
        return $this->frontDesk($user);
    }

    public function delete(User $user, Message $message): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function frontDesk(User $user): bool
    {
        return $this->hasRole($user, StaffRole::Administrator, StaffRole::Owner, StaffRole::Manager, StaffRole::Receptionist);
    }
}
