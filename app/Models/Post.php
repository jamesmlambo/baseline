<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\Concerns\HasWebImages;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;

/**
 * News, events, announcements and promotions (CMS-02). Status changes go through PostWorkflow.
 */
#[Fillable([
    'type', 'status', 'title', 'slug', 'excerpt', 'body', 'event_starts_at', 'event_ends_at', 'event_location',
    'meta_title', 'meta_description', 'published_at', 'author_id', 'submitted_by', 'published_by', 'is_demo',
])]
class Post extends Model implements HasMedia
{
    use HasWebImages, LogsActivity;

    protected function casts(): array
    {
        return [
            'type' => PostType::class,
            'status' => PostStatus::class,
            'event_starts_at' => 'immutable_datetime',
            'event_ends_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('website')
            ->logOnly(['type', 'status', 'title', 'slug', 'excerpt', 'body', 'published_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Published and not scheduled for the future.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', PostStatus::Published)->where('published_at', '<=', now());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isVisible(): bool
    {
        return $this->status === PostStatus::Published && $this->published_at?->isPast();
    }
}
