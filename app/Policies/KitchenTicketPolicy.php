<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\KitchenTicket;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * The kitchen queue (LSR-04): kitchen staff, reception, managers and the owner.
 */
class KitchenTicketPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->hasRole($user, StaffRole::KitchenStaff, StaffRole::Owner, StaffRole::Manager, StaffRole::Receptionist);
    }

    public function view(User $user, KitchenTicket $ticket): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, KitchenTicket $ticket): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, KitchenTicket $ticket): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
