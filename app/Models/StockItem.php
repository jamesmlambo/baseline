<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Towels, toiletries, coffee, sugar, cleaning supplies… (HK-09). The quantity on hand is always
 * the sum of its movements; it is never typed in.
 */
#[Fillable(['name', 'category', 'unit', 'reorder_level', 'is_active', 'is_demo'])]
class StockItem extends Model
{
    use LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['unit' => 'each', 'is_active' => true, 'is_demo' => false];

    protected function casts(): array
    {
        return [
            'reorder_level' => 'integer',
            'low_stock_alerted_at' => 'datetime',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('stock')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function onHand(): int
    {
        return (int) ($this->movements_sum_quantity ?? $this->movements()->sum('quantity'));
    }

    public function isLow(): bool
    {
        return $this->reorder_level !== null && $this->onHand() <= $this->reorder_level;
    }

    /**
     * @param  Builder<StockItem>  $query
     */
    public function scopeWithOnHand(Builder $query): void
    {
        $query->withSum('movements', 'quantity');
    }

    /**
     * Items at or below their reorder level.
     *
     * @param  Builder<StockItem>  $query
     */
    public function scopeLow(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereNotNull('reorder_level')
            ->whereRaw('(select coalesce(sum(quantity), 0) from stock_movements where stock_movements.stock_item_id = stock_items.id) <= stock_items.reorder_level');
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
