<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksWebsiteRoles;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared rules for FAQs and amenities: content editors and publishers may manage them.
 */
class WebsiteContentPolicy
{
    use ChecksWebsiteRoles;

    public function viewAny(User $user): bool
    {
        return $this->editsWebsite($user);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->editsWebsite($user);
    }

    public function create(User $user): bool
    {
        return $this->editsWebsite($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->editsWebsite($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->editsWebsite($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->editsWebsite($user);
    }
}
