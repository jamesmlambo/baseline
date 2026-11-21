<?php

declare(strict_types=1);

namespace App\Services\Reception;

use App\Enums\BookingStatus;
use App\Models\Room;
use App\Models\RoomNight;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds the room-by-date grid for reception (REC-02) straight from the room-night ledger,
 * so it always shows exactly what the booking engine holds.
 */
class RoomCalendar
{
    /**
     * @return array{dates: list<CarbonImmutable>, rooms: list<array{room: Room, cells: list<array<string, mixed>>}>}
     */
    public function build(CarbonImmutable $start, int $days = 14): array
    {
        $start = $start->startOfDay();
        $end = $start->addDays($days);
        $dates = [];

        for ($date = $start; $date->lessThan($end); $date = $date->addDay()) {
            $dates[] = $date;
        }

        $rooms = Room::query()->with('accommodationType')->orderBy('sort_order')->orderBy('id')->get();

        /** @var Collection<string, RoomNight> $claims keyed "roomId|Y-m-d" */
        $claims = RoomNight::query()
            ->active()
            ->where('night_date', '>=', $start->toDateString())
            ->where('night_date', '<', $end->toDateString())
            ->with(['bookingRoom.booking', 'roomBlock'])
            ->get()
            ->keyBy(fn (RoomNight $night): string => $night->room_id.'|'.$night->night_date->toDateString());

        $rows = [];

        foreach ($rooms as $room) {
            $cells = [];

            foreach ($dates as $date) {
                $night = $claims->get($room->id.'|'.$date->toDateString());
                $cells[] = $this->cell($night, $date, $start);
            }

            $rows[] = ['room' => $room, 'cells' => $cells];
        }

        return ['dates' => $dates, 'rooms' => $rows];
    }

    /**
     * @return array<string, mixed>
     */
    private function cell(?RoomNight $night, CarbonImmutable $date, CarbonImmutable $start): array
    {
        if ($night === null) {
            return ['kind' => 'free'];
        }

        if ($night->roomBlock !== null) {
            return [
                'kind' => 'block',
                'label' => $night->roomBlock->reason->label(),
                'starts' => $night->roomBlock->starts_on->isSameDay($date) || $date->isSameDay($start),
            ];
        }

        $booking = $night->bookingRoom?->booking;

        if ($booking === null) {
            return ['kind' => 'free'];
        }

        return [
            'kind' => match ($booking->status) {
                BookingStatus::Hold, BookingStatus::Pending, BookingStatus::Quote => 'hold',
                BookingStatus::CheckedIn => 'in_house',
                BookingStatus::CheckedOut => 'departed',
                default => 'confirmed',
            },
            'booking' => $booking,
            'label' => $booking->lead_guest_name,
            'starts' => $booking->arrival_date->isSameDay($date) || $date->isSameDay($start),
            'arrival' => $booking->arrival_date->isSameDay($date),
        ];
    }
}
