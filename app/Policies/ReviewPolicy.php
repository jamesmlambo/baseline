<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Review;
use App\Models\User;
use App\Policies\Concerns\ChecksWebsiteRoles;

/**
 * Reviews are moderated by the people who publish website content (CMS-02 roles).
 */
class ReviewPolicy
{
    use ChecksWebsiteRoles;

    public function viewAny(User $user): bool
    {
        return $this->editsWebsite($user);
    }

    public function view(User $user, Review $review): bool
    {
        return $this->editsWebsite($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Review $review): bool
    {
        return false;
    }

    public function moderate(User $user, Review $review): bool
    {
        return $this->publishesWebsite($user);
    }

    public function delete(User $user, Review $review): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
