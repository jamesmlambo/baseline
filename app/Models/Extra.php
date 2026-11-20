<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\ExtraPricingUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Something added to a stay, such as breakfast or dinner (BOOK-02, WA-02).
 */
#[Fillable(['name', 'description', 'pricing_unit', 'price_cents', 'is_active', 'is_bookable_online', 'is_demo', 'sort_order'])]
class Extra extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'pricing_unit' => ExtraPricingUnit::class,
            'price_cents' => MoneyCast::class,
            'is_active' => 'boolean',
            'is_bookable_online' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('inventory')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @param  Builder<Extra>  $query
     */
    public function scopeOnline(Builder $query): void
    {
        $query->where('is_active', true)->where('is_bookable_online', true)->orderBy('sort_order')->orderBy('name');
    }
}
