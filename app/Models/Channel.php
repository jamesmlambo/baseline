<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An external sales channel such as Booking.com or LekkeSlaap (DEC-03). Its driver is "null"
 * (not connected) until a channel manager is chosen (PEND-07/08).
 */
#[Fillable(['code', 'name', 'driver', 'is_enabled', 'stale_after_minutes'])]
class Channel extends Model
{
    use LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['driver' => 'null', 'is_enabled' => false, 'stale_after_minutes' => 60];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'stale_after_minutes' => 'integer',
            'last_successful_sync_at' => 'datetime',
            'last_attempt_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('channels')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function bookingSource(): BookingSource
    {
        return BookingSource::tryFrom($this->code) ?? BookingSource::Other;
    }

    /**
     * CHN-04: an enabled channel whose last good sync is older than its threshold (or never happened).
     */
    public function isStale(): bool
    {
        return $this->is_enabled
            && ($this->last_successful_sync_at === null || $this->last_successful_sync_at->addMinutes($this->stale_after_minutes)->isPast());
    }

    /**
     * @return HasMany<ChannelRoomType, $this>
     */
    public function roomTypes(): HasMany
    {
        return $this->hasMany(ChannelRoomType::class);
    }

    /**
     * @return HasMany<IcalLink, $this>
     */
    public function icalLinks(): HasMany
    {
        return $this->hasMany(IcalLink::class);
    }

    /**
     * @return HasMany<ChannelSyncRun, $this>
     */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(ChannelSyncRun::class)->latest('started_at');
    }
}
