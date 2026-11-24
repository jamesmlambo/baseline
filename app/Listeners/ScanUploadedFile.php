<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\Security\FileScanner;
use App\Support\MediaFile;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

/**
 * SEC-08: every uploaded file goes past the scanner. The result is stored on the file; an
 * infected file is removed at once and the removal is logged.
 */
class ScanUploadedFile
{
    public function __construct(private readonly FileScanner $scanner) {}

    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $media = $event->media;
        $result = MediaFile::withLocalCopy($media, fn (string $path): string => $this->scanner->scan($path));

        if ($result === FileScanner::INFECTED) {
            Log::warning("Upload removed by virus scanner: media #{$media->id} ({$media->mime_type}).");
            activity('security')->event('upload_infected')->withProperties(['media_id' => $media->id, 'model' => $media->model_type])->log('Infected upload removed');
            $media->delete();

            return;
        }

        $media->setCustomProperty('virus_scan', $result);
        $media->saveQuietly();
    }
}
