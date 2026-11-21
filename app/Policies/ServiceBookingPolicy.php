<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ServiceBooking;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * Leisure bookings are taken and managed by the front desk. Nothing is deleted.
 */
class ServiceBookingPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->isFrontDesk($user);
    }

    public function view(User $user, ServiceBooking $booking): bool
    {
        return $this->isFrontDesk($user);
    }

    public function create(User $user): bool
    {
        return $this->isFrontDesk($user);
    }

    public function update(User $user, ServiceBooking $booking): bool
    {
        return $this->isFrontDesk($user);
    }

    public function delete(User $user, ServiceBooking $booking): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
