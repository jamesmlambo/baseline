<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Enums\StaffRole;
use App\Models\User;

trait ChecksWebsiteRoles
{
    use ChecksStaffRoles;

    /**
     * Content editors plus the people who can publish.
     */
    protected function editsWebsite(User $user): bool
    {
        return $this->hasRole($user, StaffRole::ContentEditor) || $this->publishesWebsite($user);
    }

    /**
     * Manager, Owner or Administrator approve and publish (CMS-02).
     */
    protected function publishesWebsite(User $user): bool
    {
        return $this->hasRole($user, StaffRole::Administrator, StaffRole::Owner, StaffRole::Manager);
    }
}
