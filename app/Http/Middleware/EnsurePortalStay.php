<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Portal\PortalAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request into the guest portal only with a valid stay session (GST-06). The session is
 * re-checked on every page, so checkout, a room move or a QR change ends access immediately.
 */
class EnsurePortalStay
{
    public function __construct(private readonly PortalAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $booking = $this->access->currentStay($request);

        if ($booking === null) {
            return redirect()->route('portal.start')->with('status', 'Your guest portal session has ended. Scan the QR code in your room to sign in again.');
        }

        $request->attributes->set('portal_booking', $booking->load(['activeBookingRooms.room', 'guest']));

        return $next($request);
    }
}
