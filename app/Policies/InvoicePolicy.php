<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * Invoices (PAY-02/03). The front desk (reception and the finance group) views, issues and
 * sends them. Issued invoices are never edited or deleted: correct them with a credit note.
 */
class InvoicePolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->frontDesk($user);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->frontDesk($user);
    }

    public function create(User $user): bool
    {
        return $this->frontDesk($user);
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $this->frontDesk($user) && $invoice->voided_at === null;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return false;
    }

    public function delete(User $user, Invoice $invoice): bool
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
