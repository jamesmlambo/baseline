<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Gives code that needs a real file on disk (image cleaning, virus scanning) a local copy of an
 * uploaded file, wherever it is stored. On the local disk that is the file itself; on cloud
 * storage (Laravel Cloud buckets) it is downloaded to a temporary file and, if changed, uploaded back.
 */
final class MediaFile
{
    /**
     * @template T
     *
     * @param  callable(string): T  $callback  receives the local path
     * @param  bool  $writeBack  upload the local copy again afterwards (it was changed)
     * @return T
     */
    public static function withLocalCopy(Media $media, callable $callback, bool $writeBack = false): mixed
    {
        if (config("filesystems.disks.{$media->disk}.driver") === 'local') {
            return $callback($media->getPath());
        }

        $disk = Storage::disk($media->disk);
        $key = $media->getPathRelativeToRoot();
        // Keep the extension: image tools pick the format from it.
        $placeholder = (string) tempnam(sys_get_temp_dir(), 'media_');
        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION);
        $local = $extension !== '' ? $placeholder.'.'.$extension : $placeholder;

        try {
            file_put_contents($local, (string) $disk->get($key));
            $result = $callback($local);

            if ($writeBack) {
                $disk->put($key, (string) file_get_contents($local));
            }

            return $result;
        } finally {
            @unlink($local);
            @unlink($placeholder);
        }
    }
}
