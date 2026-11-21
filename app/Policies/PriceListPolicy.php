<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\PriceList;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

class PriceListPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->frontDesk($user);
    }

    public function view(User $user, PriceList $priceList): bool
    {
        return $this->frontDesk($user);
    }

    public function create(User $user): bool
    {
        return $this->frontDesk($user);
    }

    public function update(User $user, PriceList $priceList): bool
    {
        return false;
    }

    public function revoke(User $user, PriceList $priceList): bool
    {
        return $this->frontDesk($user) && $priceList->revoked_at === null;
    }

    public function delete(User $user, PriceList $priceList): bool
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
