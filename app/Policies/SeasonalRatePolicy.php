<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SeasonalRate;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

class SeasonalRatePolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->viewsInventory($user);
    }

    public function view(User $user, SeasonalRate $rate): bool
    {
        return $this->viewsInventory($user);
    }

    public function create(User $user): bool
    {
        return $this->managesInventory($user);
    }

    public function update(User $user, SeasonalRate $rate): bool
    {
        return $this->managesInventory($user);
    }

    public function delete(User $user, SeasonalRate $rate): bool
    {
        return $this->managesInventory($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->managesInventory($user);
    }
}
