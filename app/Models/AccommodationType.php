<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\AccommodationTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A sellable category with shared pricing, e.g. "Luxury". Guests book types (INV-02).
 */
#[Fillable([
    'name', 'slug', 'summary', 'description', 'base_occupancy', 'max_occupancy',
    'base_nightly_rate_cents', 'extra_guest_nightly_cents', 'sort_order', 'is_active', 'is_demo',
])]
class AccommodationType extends Model
{
    /** @use HasFactory<AccommodationTypeFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'base_nightly_rate_cents' => MoneyCast::class,
            'extra_guest_nightly_cents' => MoneyCast::class,
            'base_occupancy' => 'integer',
            'max_occupancy' => 'integer',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventory')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * @return HasMany<SeasonalRate, $this>
     */
    public function seasonalRates(): HasMany
    {
        return $this->hasMany(SeasonalRate::class);
    }

    /**
     * @param  Builder<AccommodationType>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
