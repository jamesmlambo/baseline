<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * When guests may order from a menu category (LSR-04), e.g. breakfast orders 18:00–10:00.
 * A window that closes before it opens runs overnight. Categories without a rule are always open.
 */
#[Fillable(['category', 'orders_open_at', 'orders_close_at', 'note'])]
class MenuCategoryRule extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('menu')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function isOpenAt(CarbonImmutable $at): bool
    {
        $time = $at->setTimezone('Africa/Johannesburg')->format('H:i');
        $open = substr((string) $this->orders_open_at, 0, 5);
        $close = substr((string) $this->orders_close_at, 0, 5);

        return $open <= $close
            ? $time >= $open && $time < $close
            : $time >= $open || $time < $close;
    }

    public function windowLabel(): string
    {
        return substr((string) $this->orders_open_at, 0, 5).'–'.substr((string) $this->orders_close_at, 0, 5);
    }

    /**
     * Categories (from the given list) that cannot be ordered right now.
     *
     * @param  list<string>  $categories
     * @return array<string, MenuCategoryRule>
     */
    public static function closedAmong(array $categories, CarbonImmutable $at): array
    {
        return static::query()->whereIn('category', $categories)->get()
            ->reject(fn (MenuCategoryRule $rule): bool => $rule->isOpenAt($at))
            ->keyBy('category')
            ->all();
    }
}
