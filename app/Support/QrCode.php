<?php

declare(strict_types=1);

namespace App\Support;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

/**
 * Renders QR codes as PNG data URIs (GD), so they can be embedded in PDFs and pages.
 */
final class QrCode
{
    public static function pngDataUri(string $content, int $size = 180): string
    {
        $png = (new Writer(new GDLibRenderer($size, 2)))->writeString($content);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
