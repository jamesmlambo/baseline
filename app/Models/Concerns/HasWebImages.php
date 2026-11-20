<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Shared image conversions for public website images (WEB-03): WebP sizes for cards and heroes,
 * plus responsive srcsets. Originals have their metadata stripped on upload (SEC-08).
 */
trait HasWebImages
{
    use InteractsWithMedia;

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')
            ->fit(Fit::Crop, 800, 600)
            ->format('webp')
            ->quality(78)
            ->withResponsiveImages();

        $this->addMediaConversion('hero')
            ->fit(Fit::Max, 1920, 1080)
            ->format('webp')
            ->quality(80)
            ->withResponsiveImages();
    }

    /**
     * URL of a conversion, falling back to the original until queued conversions have run.
     */
    public function imageUrl(string $collection, string $conversion = 'card'): ?string
    {
        $media = $this->getFirstMedia($collection);

        if ($media === null) {
            return null;
        }

        return $media->hasGeneratedConversion($conversion) ? $media->getUrl($conversion) : $media->getUrl();
    }
}
