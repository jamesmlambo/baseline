<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Key/value system settings. Settings flagged as pending client decisions
 * (FINAL_SPECIFICATION.md §3.2) are shown with a warning in admin.
 */
#[Fillable(['group', 'key', 'label', 'value', 'type', 'is_pending_client_decision', 'decision_reference', 'help_text'])]
class Setting extends Model
{
    use LogsActivity;

    private const CACHE_KEY = 'settings.all';

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'is_pending_client_decision' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('settings')
            ->logOnly(['value', 'is_pending_client_decision'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        /** @var array<string, mixed> $all */
        $all = Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => static::query()->pluck('value', 'key')->all(),
        );

        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }
}
