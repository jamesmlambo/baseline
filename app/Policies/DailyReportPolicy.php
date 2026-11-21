<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\DailyReport;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * The report archive is for the owner and managers. Reports are generated, never typed or deleted.
 */
class DailyReportPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->hasRole($user, StaffRole::Owner, StaffRole::Manager);
    }

    public function view(User $user, DailyReport $report): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DailyReport $report): bool
    {
        return false;
    }

    public function freeze(User $user, DailyReport $report): bool
    {
        return $this->viewAny($user) && ! $report->isFrozen();
    }

    public function delete(User $user, DailyReport $report): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
