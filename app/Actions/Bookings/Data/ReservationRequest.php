<?php

declare(strict_types=1);

namespace App\Actions\Bookings\Data;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Models\AccommodationType;
use App\Models\Room;
use App\Models\User;
use App\Support\StayDates;
use Carbon\CarbonImmutable;

/**
 * Everything needed to create a reservation from the website or the front desk.
 */
final readonly class ReservationRequest
{
    /**
     * @param  array<int, int>  $extraQuantities  extra_id => quantity (0 = use the extra's pricing unit)
     */
    public function __construct(
        public AccommodationType $type,
        public StayDates $stay,
        public int $adults,
        public int $children,
        public string $firstName,
        public string $lastName,
        public ?string $email,
        public ?string $phone,
        public BookingSource $source,
        public BookingStatus $status = BookingStatus::Hold,
        public ?PaymentMethod $preferredPaymentMethod = null,
        public array $extraQuantities = [],
        public ?string $specialRequests = null,
        public bool $termsAccepted = false,
        public bool $marketingConsent = false,
        public ?CarbonImmutable $holdExpiresAt = null,
        public ?Room $room = null,
        public ?User $createdBy = null,
    ) {}

    public function guestCount(): int
    {
        return $this->adults + $this->children;
    }
}
