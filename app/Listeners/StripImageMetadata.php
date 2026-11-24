<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\MediaFile;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Throwable;

/**
 * Re-encodes uploaded images so EXIF data (GPS location, camera serials) never reaches the
 * public website (SEC-08). The image is turned upright first so phone photos don't end up sideways.
 */
class StripImageMetadata
{
    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $media = $event->media;

        if (! in_array($media->mime_type, self::IMAGE_TYPES, true)) {
            return;
        }

        try {
            $size = MediaFile::withLocalCopy($media, function (string $path): int {
                Image::load($path)->orientation()->save($path);
                clearstatcache(true, $path);

                return (int) filesize($path);
            }, writeBack: true);
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        $media->forceFill(['size' => $size])->saveQuietly();
    }
}
