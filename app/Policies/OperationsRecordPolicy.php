<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;
use Illuminate\Database\Eloquent\Model;

/**
 * Assets, maintenance schedules, checklists and effort estimates (HK-04, HK-07, HK-08):
 * the owner or a manager manages them; maintenance workers may look. Nothing is deleted.
 */
class OperationsRecordPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->managesInventory($user) || $this->hasRole($user, StaffRole::MaintenanceWorker);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->managesInventory($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->managesInventory($user);
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
