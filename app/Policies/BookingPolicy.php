<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Enums\StaffRole;
use App\Models\Booking;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * Bookings: the front desk creates and manages them. Overrides that bypass payment
 * rules are Owner-only until the client decides otherwise (PEND-06).
 */
class BookingPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->frontDesk($user);
    }

    public function view(User $user, Booking $booking): bool
    {
        return $this->frontDesk($user);
    }

    public function create(User $user): bool
    {
        return $this->frontDesk($user);
    }

    public function update(User $user, Booking $booking): bool
    {
        return $this->frontDesk($user);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $this->frontDesk($user) && $booking->status->canTransitionTo(BookingStatus::Cancelled);
    }

    public function reinstate(User $user, Booking $booking): bool
    {
        return $this->hasRole($user, StaffRole::Administrator, StaffRole::Owner, StaffRole::Manager)
            && $booking->status === BookingStatus::Expired;
    }

    /**
     * Confirming without the required payment is an override (PEND-06): Owner only for now.
     */
    public function confirmWithoutPayment(User $user, Booking $booking): bool
    {
        return $this->hasRole($user, StaffRole::Owner)
            && $booking->status->canTransitionTo(BookingStatus::Confirmed)
            && $booking->status !== BookingStatus::Expired;
    }

    public function delete(User $user, Booking $booking): bool
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
