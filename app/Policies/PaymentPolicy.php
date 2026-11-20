<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * Payments (§9). Reception records payments and can ask for refunds; only the finance group
 * (Owner, Manager, Administrator) verifies EFTs against the bank and approves refunds.
 */
class PaymentPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->frontDesk($user);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->frontDesk($user);
    }

    public function create(User $user): bool
    {
        return $this->frontDesk($user);
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function verify(User $user, Payment $payment): bool
    {
        return $this->finance($user);
    }

    public function requestRefund(User $user, Payment $payment): bool
    {
        return $this->frontDesk($user);
    }

    public function approveRefund(User $user, Payment $payment): bool
    {
        return $this->finance($user);
    }

    private function frontDesk(User $user): bool
    {
        return $this->finance($user) || $this->hasRole($user, StaffRole::Receptionist);
    }

    private function finance(User $user): bool
    {
        return $this->hasRole($user, StaffRole::Administrator, StaffRole::Owner, StaffRole::Manager);
    }
}
