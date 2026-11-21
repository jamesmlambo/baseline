<?php

declare(strict_types=1);

namespace App\Actions\Reception;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Looks up a booking from the QR printed on its documents (REC-06/07). The QR holds only an
 * opaque, revocable code — never personal data — and every lookup is written to the audit log.
 */
class ResolveReceptionCode
{
    /**
     * What the QR on a document contains: the admin scan page with the opaque code.
     * Opening it requires a signed-in staff session (and 2FA) like any admin page.
     */
    public static function urlFor(Booking $booking): string
    {
        return url('/admin/scan?code='.self::ensureCode($booking));
    }

    public function handle(string $scanned, User $by): ?Booking
    {
        $code = self::extractCode($scanned);

        if ($code === null) {
            return null;
        }

        $booking = Booking::query()->where('reception_code', $code)->first();

        activity('reception')
            ->causedBy($by)
            ->performedOn($booking ?? new Booking)
            ->event($booking ? 'document_scanned' : 'document_scan_unknown')
            ->log($booking ? 'Booking document scanned at reception' : 'Unknown document code scanned');

        return $booking;
    }

    /**
     * Accepts the full QR URL (camera or USB scanner) or just the code.
     */
    public static function extractCode(string $scanned): ?string
    {
        $scanned = trim($scanned);

        if (preg_match('#/admin/scan\?(?:.*&)?code=([A-Za-z0-9]{32})#', $scanned, $match) === 1) {
            return $match[1];
        }

        return preg_match('/^[A-Za-z0-9]{32}$/', $scanned) === 1 ? $scanned : null;
    }

    public static function ensureCode(Booking $booking): string
    {
        if ($booking->reception_code === null) {
            $booking->forceFill(['reception_code' => Str::random(32)])->save();
        }

        return $booking->reception_code;
    }

    /**
     * Revoke a leaked code: old printed QRs stop working immediately.
     */
    public static function regenerate(Booking $booking, User $by): string
    {
        $booking->forceFill(['reception_code' => Str::random(32)])->save();

        activity('reception')->causedBy($by)->performedOn($booking)->event('document_code_revoked')->log('Document QR code revoked and replaced');

        return $booking->reception_code;
    }
}
