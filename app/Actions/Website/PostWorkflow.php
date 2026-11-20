<?php

declare(strict_types=1);

namespace App\Actions\Website;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * News workflow (CMS-02): content editors write drafts and submit them for review;
 * a Manager, Owner or Administrator publishes or archives.
 */
class PostWorkflow
{
    public function submitForReview(Post $post, User $by): Post
    {
        $this->authorize($by, 'submit', $post);
        $this->guard($post, [PostStatus::Draft]);

        $post->forceFill(['status' => PostStatus::InReview, 'submitted_by' => $by->id])->save();

        return $post;
    }

    public function publish(Post $post, User $by, ?CarbonImmutable $at = null): Post
    {
        $this->authorize($by, 'publish', $post);
        $this->guard($post, [PostStatus::Draft, PostStatus::InReview, PostStatus::Archived]);

        $post->forceFill([
            'status' => PostStatus::Published,
            'published_at' => $at ?? $post->published_at ?? now(),
            'published_by' => $by->id,
        ])->save();

        return $post;
    }

    public function sendBackToDraft(Post $post, User $by): Post
    {
        $this->authorize($by, 'publish', $post);
        $this->guard($post, [PostStatus::InReview, PostStatus::Published]);

        $post->forceFill(['status' => PostStatus::Draft])->save();

        return $post;
    }

    public function archive(Post $post, User $by): Post
    {
        $this->authorize($by, 'publish', $post);
        $this->guard($post, [PostStatus::Published, PostStatus::Draft, PostStatus::InReview]);

        $post->forceFill(['status' => PostStatus::Archived])->save();

        return $post;
    }

    private function authorize(User $user, string $ability, Post $post): void
    {
        if (! $user->can($ability, $post)) {
            throw new AuthorizationException('You are not allowed to do that.');
        }
    }

    /**
     * @param  list<PostStatus>  $allowedFrom
     */
    private function guard(Post $post, array $allowedFrom): void
    {
        if (! in_array($post->status, $allowedFrom, true)) {
            throw new InvalidArgumentException("This post is {$post->status->label()} and cannot be changed that way.");
        }
    }
}
