<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AmenityCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'category', 'icon', 'description', 'sort_order', 'is_published', 'is_demo'])]
class Amenity extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'category' => AmenityCategory::class,
            'is_published' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('website')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return BelongsToMany<AccommodationType, $this>
     */
    public function accommodationTypes(): BelongsToMany
    {
        return $this->belongsToMany(AccommodationType::class);
    }

    /**
     * @param  Builder<Amenity>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->orderBy('sort_order')->orderBy('name');
    }
}
