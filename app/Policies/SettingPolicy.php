<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Setting;
use App\Models\User;

/**
 * System settings (CMS-06): Administrators and Owners only. Settings are seeded,
 * so they can never be created or deleted through the UI.
 */
class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->managesSettings($user);
    }

    public function view(User $user, Setting $setting): bool
    {
        return $this->managesSettings($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Setting $setting): bool
    {
        return $this->managesSettings($user);
    }

    public function delete(User $user, Setting $setting): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Setting $setting): bool
    {
        return false;
    }

    public function forceDelete(User $user, Setting $setting): bool
    {
        return false;
    }

    private function managesSettings(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole([
            StaffRole::Administrator->value,
            StaffRole::Owner->value,
        ]);
    }
}
