<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\RoomBlockReason;
use App\Exceptions\RoomUnavailableException;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use App\Services\Inventory\RoomNightLedger;
use App\Support\StayDates;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Takes a room out of inventory (INV-05). Fails if any night is already booked:
 * staff must move that booking first, so nothing is silently overbooked.
 */
class BlockRoom
{
    public function __construct(private readonly RoomNightLedger $ledger) {}

    public function handle(Room $room, StayDates $dates, RoomBlockReason $reason, ?string $notes = null, ?User $by = null): RoomBlock
    {
        try {
            return DB::transaction(function () use ($room, $dates, $reason, $notes, $by): RoomBlock {
                $block = RoomBlock::query()->create([
                    'room_id' => $room->id,
                    'starts_on' => $dates->arrivalString(),
                    'ends_on' => $dates->departureString(),
                    'reason' => $reason,
                    'notes' => $notes,
                    'created_by' => $by?->id,
                ]);

                $this->ledger->claimForBlock($block);

                return $block;
            });
        } catch (QueryException $exception) {
            if (RoomNightLedger::isConflict($exception)) {
                throw new RoomUnavailableException("{$room->name} already has bookings or blocks on some of those nights.");
            }

            throw $exception;
        }
    }
}
