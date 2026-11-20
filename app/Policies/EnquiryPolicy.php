<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Enquiry;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * Website enquiries contain personal data: front desk and management only. Never deleted from the UI.
 */
class EnquiryPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->handlesEnquiries($user);
    }

    public function view(User $user, Enquiry $enquiry): bool
    {
        return $this->handlesEnquiries($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Enquiry $enquiry): bool
    {
        return $this->handlesEnquiries($user);
    }

    public function delete(User $user, Enquiry $enquiry): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function handlesEnquiries(User $user): bool
    {
        return $this->hasRole($user, StaffRole::Administrator, StaffRole::Owner, StaffRole::Manager, StaffRole::Receptionist);
    }
}
