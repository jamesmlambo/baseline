<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line in the stock ledger. Never edited or deleted: mistakes are corrected with an adjustment.
 */
#[Fillable(['stock_item_id', 'quantity', 'reason', 'task_id', 'user_id', 'note'])]
class StockMovement extends Model
{
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Stock movements cannot be changed. Record an adjustment instead.'));
        static::deleting(fn (): never => throw new LogicException('Stock movements cannot be deleted. Record an adjustment instead.'));
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reason' => StockMovementReason::class,
        ];
    }

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
