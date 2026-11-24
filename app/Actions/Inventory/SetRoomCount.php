<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\AccommodationType;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sets how many bookable rooms a room type has (INV-01), from the "Number of rooms" field.
 *
 * More rooms: rooms of this type that were switched off are switched back on first, then new
 * rooms are added ("Budget Room #3") for staff to rename. Fewer rooms: rooms without upcoming
 * bookings or blocks are switched off, last in the list first. Rooms are never deleted, so
 * booking history stays intact. If too many rooms are still booked, nothing changes.
 */
class SetRoomCount
{
    public const MAX_ROOMS = 100;

    /**
     * @return array{added: int, reactivated: int, deactivated: int}
     */
    public function handle(AccommodationType $type, int $count): array
    {
        if ($count < 0 || $count > self::MAX_ROOMS) {
            throw new InvalidArgumentException('The number of rooms must be between 0 and '.self::MAX_ROOMS.'.');
        }

        return DB::transaction(function () use ($type, $count): array {
            /** @var Collection<int, Room> $rooms */
            $rooms = $type->rooms()->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
            $active = $rooms->where('is_active', true);
            $result = ['added' => 0, 'reactivated' => 0, 'deactivated' => 0];

            if ($count > $active->count()) {
                $needed = $count - $active->count();

                foreach ($rooms->where('is_active', false)->take($needed) as $room) {
                    $room->update(['is_active' => true]);
                    $result['reactivated']++;
                    $needed--;
                }

                for (; $needed > 0; $needed--) {
                    $rooms->push($this->addRoom($type, $rooms));
                    $result['added']++;
                }
            }

            if ($count < $active->count()) {
                $toRemove = $active->count() - $count;
                $booked = $this->roomsWithUpcomingClaims($active->modelKeys());
                $free = $active->reject(fn (Room $room): bool => in_array($room->id, $booked, true))->reverse();

                if ($free->count() < $toRemove) {
                    throw new InvalidArgumentException(sprintf(
                        'Only %d room(s) can be removed now: the others have upcoming bookings or blocks. Move those first, or set the number to %d.',
                        $free->count(),
                        $active->count() - $free->count(),
                    ));
                }

                foreach ($free->take($toRemove) as $room) {
                    $room->update(['is_active' => false]);
                    $result['deactivated']++;
                }
            }

            return $result;
        });
    }

    /**
     * @param  Collection<int, Room>  $existing
     */
    private function addRoom(AccommodationType $type, Collection $existing): Room
    {
        $number = $existing->count() + 1;

        while ($existing->contains('name', "{$type->name} #{$number}")) {
            $number++;
        }

        return $type->rooms()->create([
            'name' => "{$type->name} #{$number}",
            'code' => $this->uniqueCode($type, $number),
            'sort_order' => ((int) $existing->max('sort_order')) + 1,
            'is_active' => true,
            'is_demo' => $type->is_demo,
        ]);
    }

    private function uniqueCode(AccommodationType $type, int $number): string
    {
        $prefix = Str::upper(Str::limit($type->slug, 24, ''));

        do {
            $code = "{$prefix}-{$number}";
            $number++;
        } while (Room::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * Rooms holding a booking or block on tonight or any later night.
     *
     * @param  list<int>  $roomIds
     * @return list<int>
     */
    private function roomsWithUpcomingClaims(array $roomIds): array
    {
        return DB::table('room_nights')
            ->whereIn('room_id', $roomIds)
            ->whereNull('released_at')
            ->where('night_date', '>=', CarbonImmutable::today('Africa/Johannesburg')->toDateString())
            ->distinct()
            ->pluck('room_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
