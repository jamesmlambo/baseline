<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Redirect;
use App\Models\User;
use App\Policies\Concerns\ChecksWebsiteRoles;

/**
 * Redirects affect SEO and old links, so only publishers manage them.
 */
class RedirectPolicy
{
    use ChecksWebsiteRoles;

    public function viewAny(User $user): bool
    {
        return $this->publishesWebsite($user);
    }

    public function view(User $user, Redirect $redirect): bool
    {
        return $this->publishesWebsite($user);
    }

    public function create(User $user): bool
    {
        return $this->publishesWebsite($user);
    }

    public function update(User $user, Redirect $redirect): bool
    {
        return $this->publishesWebsite($user);
    }

    public function delete(User $user, Redirect $redirect): bool
    {
        return $this->publishesWebsite($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->publishesWebsite($user);
    }
}
