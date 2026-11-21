<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockItem;
use App\Models\User;
use App\Policies\Concerns\ChecksStaffRoles;

/**
 * HK-09: staff who handle stock record what they receive and use; only the owner or a manager
 * adds items, sets reorder levels or records stock-take corrections.
 */
class StockItemPolicy
{
    use ChecksStaffRoles;

    public function viewAny(User $user): bool
    {
        return $this->isOperationsStaff($user);
    }

    public function view(User $user, StockItem $item): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->managesInventory($user);
    }

    public function update(User $user, StockItem $item): bool
    {
        return $this->managesInventory($user);
    }

    public function recordMovement(User $user, StockItem $item): bool
    {
        return $item->is_active && $this->isOperationsStaff($user);
    }

    public function adjust(User $user, StockItem $item): bool
    {
        return $this->managesInventory($user);
    }

    public function delete(User $user, StockItem $item): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
