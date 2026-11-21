<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * Message wording: the Owner, a Manager or an Administrator may change it. Templates are
 * seeded per automation, so they cannot be created or deleted from the screen.
 */
class MessageTemplatePolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, MessageTemplate $template): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MessageTemplate $template): bool
    {
        return $this->manages($user);
    }

    public function delete(User $user, MessageTemplate $template): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function manages(User $user): bool
    {
        return $this->hasRole($user, StaffRole::Administrator, StaffRole::Owner, StaffRole::Manager);
    }
}
