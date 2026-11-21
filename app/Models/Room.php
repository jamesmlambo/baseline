<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoomCleaningStatus;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A specific named physical room. Assigned to bookings at booking time (DEC-02).
 */
#[Fillable(['accommodation_type_id', 'name', 'code', 'portal_token', 'internal_notes', 'sort_order', 'is_active', 'is_demo'])]
#[Hidden(['portal_token'])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory, LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['cleaning_status' => 'ready'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
            'cleaning_status' => RoomCleaningStatus::class,
            'cleaning_status_changed_at' => 'datetime',
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
     * @return BelongsTo<AccommodationType, $this>
     */
    public function accommodationType(): BelongsTo
    {
        return $this->belongsTo(AccommodationType::class);
    }

    /**
     * @return HasMany<RoomNight, $this>
     */
    public function roomNights(): HasMany
    {
        return $this->hasMany(RoomNight::class);
    }

    /**
     * @return HasMany<RoomBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(RoomBlock::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * The opaque code printed in this room's QR (GST-01). Created on first use.
     */
    public function ensurePortalToken(): string
    {
        if ($this->portal_token === null) {
            $this->forceFill(['portal_token' => Str::random(32)])->save();
        }

        return $this->portal_token;
    }

    /**
     * Replace the QR code (e.g. a printed card was taken). Everyone signed in through
     * the old code is signed out on their next page (GST-06).
     */
    public function rotatePortalToken(): string
    {
        $this->forceFill(['portal_token' => Str::random(32)])->save();

        return $this->portal_token;
    }

    public function portalUrl(): string
    {
        return route('portal.room', $this->ensurePortalToken());
    }

    /**
     * @param  Builder<Room>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
