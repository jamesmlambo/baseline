<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\BookingRoom;
use App\Models\RoomBlock;
use App\Support\StayDates;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Writes and releases room-night claims (INV-03). Must be called inside a transaction.
 *
 * Conflicts are detected by PostgreSQL (partial unique index on active claims,
 * exclusion constraint on booking rooms), so two concurrent requests can never
 * both claim the same room night.
 */
class RoomNightLedger
{
    /** PostgreSQL unique_violation and exclusion_violation. */
    private const CONFLICT_SQLSTATES = ['23505', '23P01'];

    public function claimForBookingRoom(BookingRoom $bookingRoom, StayDates $stay): void
    {
        $this->insertClaims($bookingRoom->room_id, $stay, ['booking_room_id' => $bookingRoom->id]);
    }

    public function claimForBlock(RoomBlock $block): void
    {
        $this->insertClaims(
            $block->room_id,
            new StayDates($block->starts_on, $block->ends_on),
            ['room_block_id' => $block->id],
        );
    }

    public function releaseBookingRoom(BookingRoom $bookingRoom, string $reason): void
    {
        $now = now();

        $bookingRoom->roomNights()->whereNull('released_at')->update(['released_at' => $now, 'updated_at' => $now]);
        $bookingRoom->forceFill(['released_at' => $now, 'release_reason' => $reason])->save();
    }

    public function releaseBlock(RoomBlock $block): void
    {
        $block->roomNights()->whereNull('released_at')->update(['released_at' => now(), 'updated_at' => now()]);
    }

    public static function isConflict(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), self::CONFLICT_SQLSTATES, true)
            || in_array($exception->errorInfo[0] ?? null, self::CONFLICT_SQLSTATES, true);
    }

    /**
     * @param  array<string, int>  $owner
     */
    private function insertClaims(int $roomId, StayDates $stay, array $owner): void
    {
        $now = now();

        $rows = array_map(fn ($night): array => [
            'room_id' => $roomId,
            'night_date' => $night->toDateString(),
            'booking_room_id' => $owner['booking_room_id'] ?? null,
            'room_block_id' => $owner['room_block_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $stay->nightDates());

        DB::table('room_nights')->insert($rows);
    }
}
