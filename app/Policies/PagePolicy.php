<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Page;
use App\Models\User;
use App\Policies\Concerns\ChecksWebsiteRoles;

/**
 * Website pages. System pages (with a key) can be edited but never deleted.
 */
class PagePolicy
{
    use ChecksWebsiteRoles;

    public function viewAny(User $user): bool
    {
        return $this->editsWebsite($user);
    }

    public function view(User $user, Page $page): bool
    {
        return $this->editsWebsite($user);
    }

    public function create(User $user): bool
    {
        return $this->publishesWebsite($user);
    }

    public function update(User $user, Page $page): bool
    {
        return $this->editsWebsite($user);
    }

    public function delete(User $user, Page $page): bool
    {
        return ! $page->isSystemPage() && $this->publishesWebsite($user);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
