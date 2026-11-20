<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use App\Policies\Concerns\ChecksWebsiteRoles;

class PostPolicy
{
    use ChecksWebsiteRoles;

    public function viewAny(User $user): bool
    {
        return $this->editsWebsite($user);
    }

    public function view(User $user, Post $post): bool
    {
        return $this->editsWebsite($user);
    }

    public function create(User $user): bool
    {
        return $this->editsWebsite($user);
    }

    /**
     * Content editors may only edit drafts; publishers may edit anything.
     */
    public function update(User $user, Post $post): bool
    {
        if ($this->publishesWebsite($user)) {
            return true;
        }

        return $this->editsWebsite($user) && $post->status === PostStatus::Draft;
    }

    public function submit(User $user, Post $post): bool
    {
        return $this->editsWebsite($user);
    }

    public function publish(User $user, Post $post): bool
    {
        return $this->publishesWebsite($user);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->publishesWebsite($user) && $post->status !== PostStatus::Published;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
