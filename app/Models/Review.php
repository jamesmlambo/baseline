<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A verified-stay review (GST-07). Only published after moderation and with the guest's consent
 * (also enforced in the database).
 */
#[Fillable(['booking_id', 'rating', 'comment', 'display_name', 'consent_to_publish', 'status', 'moderated_by', 'moderated_at'])]
class Review extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'consent_to_publish' => 'boolean',
            'status' => ReviewStatus::class,
            'moderated_at' => 'immutable_datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * @param  Builder<Review>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ReviewStatus::Approved)->where('consent_to_publish', true);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
