<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Services\Bookings\BookingStateMachine;
use App\Services\Payments\PaymentLedger;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Cancels a booking and frees its rooms. Money already paid is not refunded automatically:
 * the cancellation policy is pending (PEND-05), so staff request a refund separately.
 */
class CancelBooking
{
    public function __construct(
        private readonly BookingStateMachine $stateMachine,
        private readonly PaymentLedger $payments,
    ) {}

    public function handle(Booking $booking, string $reason, User $by): Booking
    {
        if (! $by->can('cancel', $booking)) {
            throw new AuthorizationException('You cannot cancel this booking.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required to cancel a booking.');
        }

        $this->stateMachine->transition($booking, BookingStatus::Cancelled, $reason, $by);
        $this->payments->sync($booking);

        return $booking->refresh();
    }
}
