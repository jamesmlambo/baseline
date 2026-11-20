<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\RoomBlock;
use App\Models\User;
use App\Services\Inventory\RoomNightLedger;
use Illuminate\Support\Facades\DB;

/**
 * Puts a blocked room back into inventory. The block stays as history.
 */
class ReleaseRoomBlock
{
    public function __construct(private readonly RoomNightLedger $ledger) {}

    public function handle(RoomBlock $block, ?User $by = null): RoomBlock
    {
        if (! $block->isActive()) {
            return $block;
        }

        return DB::transaction(function () use ($block, $by): RoomBlock {
            $this->ledger->releaseBlock($block);
            $block->forceFill(['released_at' => now(), 'released_by' => $by?->id])->save();

            return $block;
        });
    }
}
