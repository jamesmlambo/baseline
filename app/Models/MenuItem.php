<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Something guests can order from the portal (GST-07). Kitchen tickets and cut-off times come with M10.
 */
#[Fillable(['category', 'name', 'description', 'allergens', 'price_cents', 'is_available', 'is_demo', 'sort_order'])]
class MenuItem extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'price_cents' => MoneyCast::class,
            'is_available' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('menu')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @param  Builder<MenuItem>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_available', true)->orderBy('category')->orderBy('sort_order')->orderBy('name');
    }
}
