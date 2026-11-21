<?php

declare(strict_types=1);

namespace App\Actions\Reception;

use App\Enums\BookingStatus;
use App\Enums\FrontDeskLogType;
use App\Exceptions\OutstandingBalanceException;
use App\Models\Booking;
use App\Models\User;
use App\Services\Bookings\BookingStateMachine;
use App\Services\Messaging\BookingMessages;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Checks a guest out at reception (CHK-01/02). A zero balance is the default gate; the owner may
 * override with a reason (PEND-06). Sends the farewell message (M6); room turnover tasks hook in with M8.
 */
class CheckOut
{
    public function __construct(
        private readonly BookingStateMachine $stateMachine,
        private readonly BookingMessages $messages,
    ) {}

    /**
     * @return array{booking: Booking, keys_outstanding: int}
     */
    public function handle(Booking $booking, User $by, ?string $overrideReason = null): array
    {
        if (! $by->can('update', $booking)) {
            throw new AuthorizationException('You cannot check guests out.');
        }

        if ($booking->status !== BookingStatus::CheckedIn) {
            throw new InvalidArgumentException('Only checked-in guests can be checked out.');
        }

        $balance = $booking->balanceDue();
        $reason = 'Checked out at reception';

        if ($balance->cents > 0) {
            if (blank($overrideReason)) {
                throw OutstandingBalanceException::for($balance);
            }

            if (! $by->can('overrideCheckoutBalance', $booking)) {
                throw new AuthorizationException('Only the owner can check a guest out with money still owed.');
            }

            $reason .= " with {$balance->format()} outstanding (owner override: {$overrideReason})";
        }

        $this->stateMachine->transition($booking, BookingStatus::CheckedOut, $reason, $by);
        $booking->forceFill(['checked_out_at' => now(), 'checked_out_by' => $by->id])->save();

        // CHK-03: thank-you / safe-travels, sent once (idempotency key) even if checkout is retried.
        $this->messages->farewell($booking);

        return ['booking' => $booking->refresh(), 'keys_outstanding' => self::keysOutstanding($booking)];
    }

    public static function keysOutstanding(Booking $booking): int
    {
        $logs = $booking->frontDeskLogs()->get();

        return max(0, $logs->where('type', FrontDeskLogType::KeyIssued)->sum('quantity')
            - $logs->where('type', FrontDeskLogType::KeyReturned)->sum('quantity')
            - $logs->where('type', FrontDeskLogType::KeyLost)->sum('quantity'));
    }
}
