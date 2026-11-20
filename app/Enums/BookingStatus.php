<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Booking state machine (FINAL_SPECIFICATION §7). Payment status is separate.
 */
enum BookingStatus: string
{
    case Quote = 'quote';
    case Hold = 'hold';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Quote => 'Quote',
            self::Hold => 'Hold',
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::CheckedIn => 'Checked in',
            self::CheckedOut => 'Checked out',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No-show',
            self::Expired => 'Expired',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Quote => [self::Hold, self::Pending, self::Confirmed, self::Expired, self::Cancelled],
            self::Hold => [self::Pending, self::Confirmed, self::Expired, self::Cancelled],
            self::Pending => [self::Confirmed, self::Cancelled, self::Expired],
            self::Confirmed => [self::CheckedIn, self::Cancelled, self::NoShow],
            self::CheckedIn => [self::CheckedOut],
            self::CheckedOut, self::Cancelled, self::NoShow, self::Expired => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * Whether entering this status gives the booking's room nights back to inventory.
     */
    public function releasesInventory(): bool
    {
        return in_array($this, [self::Cancelled, self::NoShow, self::Expired], true);
    }

    /**
     * Whether a booking in this status still holds room nights.
     */
    public function holdsInventory(): bool
    {
        return in_array($this, [self::Hold, self::Pending, self::Confirmed, self::CheckedIn], true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
