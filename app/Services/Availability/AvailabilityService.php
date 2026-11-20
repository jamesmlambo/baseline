<?php

declare(strict_types=1);

namespace App\Services\Availability;

use App\Models\AccommodationType;
use App\Models\Room;
use App\Services\Pricing\RateCalculator;
use App\Support\StayDates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The single source of availability (FINAL_SPECIFICATION §1). The website, reception,
 * WhatsApp price lists and channel imports must all ask this service.
 *
 * Results are advisory: the database constraints are what actually prevent double-booking,
 * and PlaceBooking re-checks inside its transaction (INV-06).
 */
class AvailabilityService
{
    public function __construct(private readonly RateCalculator $rates) {}

    /**
     * Active rooms of a type with no active claim on any night of the stay.
     *
     * @return Builder<Room>
     */
    public function availableRoomsQuery(AccommodationType $type, StayDates $stay): Builder
    {
        return Room::query()
            ->active()
            ->where('accommodation_type_id', $type->id)
            ->whereNotExists(fn (QueryBuilder $query) => $query
                ->selectRaw('1')
                ->from('room_nights')
                ->whereColumn('room_nights.room_id', 'rooms.id')
                ->whereNull('room_nights.released_at')
                ->where('room_nights.night_date', '>=', $stay->arrivalString())
                ->where('room_nights.night_date', '<', $stay->departureString()))
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return Collection<int, Room>
     */
    public function availableRooms(AccommodationType $type, StayDates $stay): Collection
    {
        return $this->availableRoomsQuery($type, $stay)->get();
    }

    public function isRoomAvailable(Room $room, StayDates $stay): bool
    {
        return $room->is_active && ! $room->roomNights()
            ->active()
            ->where('night_date', '>=', $stay->arrivalString())
            ->where('night_date', '<', $stay->departureString())
            ->exists();
    }

    /**
     * Availability and price for every active type that fits the party.
     *
     * @return list<AvailabilityResult>
     */
    public function search(StayDates $stay, int $adults, int $children = 0): array
    {
        $guests = $adults + $children;

        return AccommodationType::query()
            ->active()
            ->where('max_occupancy', '>=', $guests)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (AccommodationType $type): AvailabilityResult => new AvailabilityResult(
                type: $type,
                availableRooms: $this->availableRoomsQuery($type, $stay)->count(),
                quote: $this->rates->quote($type, $stay, $adults, $children),
            ))
            ->values()
            ->all();
    }
}
