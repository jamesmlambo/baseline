<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Old-site URL redirects (WEB-05). Paths are stored normalised: leading slash, no trailing slash, lower case.
 */
#[Fillable(['from_path', 'to_path', 'status_code', 'is_active'])]
class Redirect extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_active' => 'boolean',
            'hits' => 'integer',
            'last_hit_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Redirect $redirect): void {
            $redirect->from_path = self::normalisePath($redirect->from_path);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('website')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    public static function normalisePath(string $path): string
    {
        $path = parse_url(trim($path), PHP_URL_PATH) ?: '/';
        $path = '/'.trim(strtolower(rawurldecode($path)), '/');

        return $path;
    }
}
