<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * CHN-02: the limited iCal fallback for one physical room on one channel. Export = a secret
 * calendar link the channel can read; import = the channel's calendar for that room.
 * iCal carries dates only — it is not a channel manager.
 */
#[Fillable(['channel_id', 'room_id', 'export_enabled', 'import_url'])]
#[Hidden(['export_token'])]
class IcalLink extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['export_enabled' => false];

    protected static function booted(): void
    {
        static::creating(function (IcalLink $link): void {
            $link->export_token ??= Str::random(40);
        });
    }

    protected function casts(): array
    {
        return [
            'export_enabled' => 'boolean',
            'last_imported_at' => 'datetime',
        ];
    }

    public function exportUrl(): string
    {
        return route('ical.export', $this->export_token);
    }

    public function rotateToken(): void
    {
        $this->forceFill(['export_token' => Str::random(40)])->save();
    }

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return HasMany<IcalEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(IcalEvent::class);
    }
}
