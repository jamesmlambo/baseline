<?php

declare(strict_types=1);

namespace App\Actions\Portal;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Publishes or rejects a guest review (GST-07). A review is only published with the guest's consent.
 */
class ModerateReview
{
    public function handle(Review $review, ReviewStatus $decision, User $moderator): Review
    {
        if (! $moderator->can('moderate', $review)) {
            throw new AuthorizationException('You are not allowed to moderate reviews.');
        }

        if ($decision === ReviewStatus::Pending) {
            throw new InvalidArgumentException('Choose publish or reject.');
        }

        if ($decision === ReviewStatus::Approved && ! $review->consent_to_publish) {
            throw new InvalidArgumentException('The guest did not agree to publish this review.');
        }

        $review->forceFill([
            'status' => $decision,
            'moderated_by' => $moderator->id,
            'moderated_at' => now(),
        ])->save();

        activity('reviews')
            ->performedOn($review)
            ->causedBy($moderator)
            ->event($decision->value)
            ->log($decision === ReviewStatus::Approved ? 'Review published' : 'Review rejected');

        return $review;
    }
}
