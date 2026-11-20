<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * A line on a quotation or invoice. Lines are written once with their document and never changed.
 */
#[Fillable(['description', 'quantity', 'unit_price_cents', 'total_cents', 'sort_order'])]
class DocumentLine extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price_cents' => MoneyCast::class,
            'total_cents' => MoneyCast::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Lines on issued documents cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Lines on issued documents cannot be deleted.'));
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function document(): MorphTo
    {
        return $this->morphTo();
    }
}
