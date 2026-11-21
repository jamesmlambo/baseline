<?php

declare(strict_types=1);

namespace App\Services\Housekeeping;

use App\Enums\StaffRole;
use App\Enums\StockMovementReason;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Task;
use App\Models\User;
use App\Notifications\StaffAlert;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * HK-09: the stock ledger. Quantities only change by adding a movement. When an item reaches its
 * reorder level the owner and managers are told once, until it is restocked.
 */
class StockLedger
{
    /**
     * @param  int  $quantity  a positive amount for received/used/damaged; a signed change for a stock-take correction
     */
    public function record(StockItem $item, StockMovementReason $reason, int $quantity, User $by, ?string $note = null, ?Task $task = null): StockMovement
    {
        if (! $by->can('recordMovement', $item)) {
            throw new AuthorizationException('You cannot record stock for this item.');
        }

        if ($reason === StockMovementReason::Adjusted) {
            if (! $by->can('adjust', $item)) {
                throw new AuthorizationException('Only a manager or the owner can correct stock levels.');
            }

            if (blank($note)) {
                throw new InvalidArgumentException('Say why the stock is being corrected.');
            }
        } elseif ($quantity <= 0) {
            throw new InvalidArgumentException('Enter a quantity of 1 or more.');
        }

        if ($quantity === 0) {
            throw new InvalidArgumentException('The quantity cannot be zero.');
        }

        $signed = $reason === StockMovementReason::Adjusted ? $quantity : $quantity * $reason->sign();

        $movement = DB::transaction(function () use ($item, $reason, $signed, $by, $note, $task): StockMovement {
            StockItem::query()->whereKey($item->id)->lockForUpdate()->first();
            $onHand = (int) StockMovement::query()->where('stock_item_id', $item->id)->sum('quantity');

            if ($onHand + $signed < 0) {
                throw new InvalidArgumentException("Only {$onHand} {$item->unit} in stock. Record what was received first, or ask a manager to correct the count.");
            }

            return StockMovement::query()->create([
                'stock_item_id' => $item->id,
                'quantity' => $signed,
                'reason' => $reason,
                'task_id' => $task?->id,
                'user_id' => $by->id,
                'note' => filled($note) ? mb_substr(trim((string) $note), 0, 255) : null,
            ]);
        });

        $this->checkLowStock($item->fresh());

        return $movement;
    }

    private function checkLowStock(StockItem $item): void
    {
        $low = $item->isLow();

        if ($low && $item->low_stock_alerted_at === null) {
            $item->forceFill(['low_stock_alerted_at' => now()])->save();

            Notification::send(
                User::query()->role([StaffRole::Owner->value, StaffRole::Manager->value])->where('is_active', true)->get(),
                new StaffAlert(
                    "Low stock: {$item->name}",
                    "{$item->onHand()} {$item->unit} left (reorder level {$item->reorder_level}).",
                    url('/admin/stock-items'),
                ),
            );
        }

        if (! $low && $item->low_stock_alerted_at !== null) {
            $item->forceFill(['low_stock_alerted_at' => null])->save();
        }
    }
}
