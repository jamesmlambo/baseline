<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\InvalidBookingTransitionException;
use App\Models\Booking;
use App\Services\Bookings\BookingStateMachine;

/**
 * Moves holds past their expiry to Expired and frees their rooms (BOOK-04).
 * Scheduled every minute in routes/console.php.
 */
class ReleaseExpiredHolds
{
    public function __construct(private readonly BookingStateMachine $stateMachine) {}

    /**
     * @return int Number of holds released
     */
    public function handle(): int
    {
        $released = 0;

        Booking::query()
            ->where('status', BookingStatus::Hold)
            ->where('hold_expires_at', '<=', now())
            ->orderBy('id')
            ->each(function (Booking $booking) use (&$released): void {
                try {
                    $this->stateMachine->transition($booking, BookingStatus::Expired, 'Hold expired automatically');
                    $released++;
                } catch (InvalidBookingTransitionException) {
                    // Status changed since we read it (e.g. paid and confirmed): leave it alone.
                }
            });

        return $released;
    }
}
