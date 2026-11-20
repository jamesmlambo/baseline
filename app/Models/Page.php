<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasWebImages;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;

/**
 * A CMS page. System pages have a fixed `key` (e.g. "about") that routes use;
 * their slug and content are editable, but they cannot be deleted.
 */
#[Fillable(['key', 'slug', 'title', 'nav_label', 'summary', 'body', 'meta_title', 'meta_description', 'is_published', 'is_demo', 'updated_by'])]
class Page extends Model implements HasMedia
{
    use HasWebImages, LogsActivity;

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('website')
            ->logOnly(['slug', 'title', 'summary', 'body', 'meta_title', 'meta_description', 'is_published'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('hero')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * @param  Builder<Page>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public static function forKey(string $key): ?self
    {
        return static::query()->published()->where('key', $key)->first();
    }

    public function isSystemPage(): bool
    {
        return $this->key !== null;
    }
}
