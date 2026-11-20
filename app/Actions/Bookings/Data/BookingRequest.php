<?php

declare(strict_types=1);

namespace App\Actions\Bookings\Data;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\User;
use App\Support\StayDates;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class BookingRequest
{
    /**
     * @param  non-empty-list<RoomRequest>  $rooms
     */
    public function __construct(
        public StayDates $stay,
        public array $rooms,
        public string $leadGuestName,
        public BookingSource $source,
        public BookingStatus $status = BookingStatus::Hold,
        public ?string $leadGuestEmail = null,
        public ?string $leadGuestPhone = null,
        public ?CarbonImmutable $holdExpiresAt = null,
        public ?string $specialRequests = null,
        public ?string $externalReference = null,
        public ?User $createdBy = null,
    ) {
        if ($rooms === []) {
            throw new InvalidArgumentException('A booking needs at least one room.');
        }

        if (! in_array($status, [BookingStatus::Quote, BookingStatus::Hold, BookingStatus::Pending, BookingStatus::Confirmed], true)) {
            throw new InvalidArgumentException("A new booking cannot start as {$status->label()}.");
        }
    }
}
