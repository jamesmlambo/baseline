<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Booking;
use App\Models\Quotation;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * Quotations (PAY-01). The front desk (reception and the finance group) views, issues and
 * sends them. Issued quotations are never edited or deleted: issue a new one instead.
 */
class QuotationPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->frontDesk($user);
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $this->frontDesk($user);
    }

    public function create(User $user): bool
    {
        return $this->frontDesk($user);
    }

    public function send(User $user, Quotation $quotation): bool
    {
        return $this->frontDesk($user) && ! $quotation->isExpired();
    }

    /**
     * Turn the quotation into a booking: anyone who can make bookings, while the quote can still be booked.
     */
    public function book(User $user, Quotation $quotation): bool
    {
        return $this->frontDesk($user) && $user->can('create', Booking::class) && $quotation->canBeBooked();
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return false;
    }

    public function delete(User $user, Quotation $quotation): bool
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
