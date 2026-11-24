<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AmenityCategory;
use BladeUI\Icons\Exceptions\SvgNotFound;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'category', 'icon', 'description', 'sort_order', 'is_published', 'is_demo'])]
class Amenity extends Model
{
    use LogsActivity;

    public const FALLBACK_ICON = 'heroicon-o-check-circle';

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
     * The amenity's icon as inline SVG (blade-icons name, e.g. "phosphor-wifi-high-duotone").
     * Falls back to a check mark when no icon is set or the name is unknown.
     */
    public function iconSvg(string $class = 'size-5'): HtmlString
    {
        return self::svgFor($this->icon, $class);
    }

    public static function svgFor(?string $icon, string $class = 'size-5'): HtmlString
    {
        try {
            return new HtmlString(svg(filled($icon) ? $icon : self::FALLBACK_ICON, $class, ['aria-hidden' => 'true'])->toHtml());
        } catch (SvgNotFound) {
            return new HtmlString(svg(self::FALLBACK_ICON, $class, ['aria-hidden' => 'true'])->toHtml());
        }
    }

    /**
     * Icon followed by the name, for admin tick boxes and lists. The name is escaped.
     */
    public function labelWithIcon(): string
    {
        return '<span style="display:inline-flex;align-items:center;gap:.4rem">'.self::svgFor($this->icon, 'size-5')->toHtml().'<span>'.e($this->name).'</span></span>';
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
