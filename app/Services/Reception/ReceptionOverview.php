<?php

declare(strict_types=1);

namespace App\Services\Reception;

use App\Enums\BookingStatus;
use App\Enums\PaymentRecordStatus;
use App\Models\Booking;
use App\Models\FrontDeskLog;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Services\Availability\AvailabilityService;
use App\Support\StayDates;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The numbers on the reception dashboard (REC-01). Queries only; nothing here changes data.
 */
class ReceptionOverview
{
    /**
     * @return Builder<Booking>
     */
    public function arrivals(?CarbonImmutable $day = null): Builder
    {
        return Booking::query()
            ->where('arrival_date', ($day ?? today())->toDateString())
            ->whereIn('status', [BookingStatus::Hold, BookingStatus::Pending, BookingStatus::Confirmed]);
    }

    /**
     * @return Builder<Booking>
     */
    public function departures(?CarbonImmutable $day = null): Builder
    {
        return Booking::query()
            ->where('departure_date', ($day ?? today())->toDateString())
            ->where('status', BookingStatus::CheckedIn);
    }

    /**
     * @return Builder<Booking>
     */
    public function inHouse(): Builder
    {
        return Booking::query()->where('status', BookingStatus::CheckedIn);
    }

    public function freeRoomsTonight(): int
    {
        $tonight = new StayDates(today(), today()->addDay());

        return Room::query()->active()->get()
            ->filter(fn (Room $room): bool => app(AvailabilityService::class)->isRoomAvailable($room, $tonight))
            ->count();
    }

    public function holdsExpiringSoon(int $hours = 2): int
    {
        return Booking::query()
            ->where('status', BookingStatus::Hold)
            ->whereBetween('hold_expires_at', [now(), now()->addHours($hours)])
            ->count();
    }

    public function eftsAwaitingVerification(): int
    {
        return Payment::query()->where('status', PaymentRecordStatus::PendingVerification)->count();
    }

    public function paymentExceptions(): int
    {
        return Payment::query()->whereNotNull('exception_reason')->count();
    }

    public function roomsBlockedToday(): int
    {
        return RoomBlock::query()
            ->whereNull('released_at')
            ->where('starts_on', '<=', today()->toDateString())
            ->where('ends_on', '>', today()->toDateString())
            ->count();
    }

    public function openComplaints(): int
    {
        return FrontDeskLog::query()->open()->count();
    }

    /**
     * In-house or arriving guests who still owe money.
     */
    public function guestsWithBalance(): int
    {
        return Booking::query()
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
            ->where('arrival_date', '<=', today()->addDay()->toDateString())
            ->get()
            ->filter(fn (Booking $booking): bool => $booking->balanceDue()->cents > 0)
            ->count();
    }
}
