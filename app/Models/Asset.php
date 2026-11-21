<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Equipment the lodge maintains: pumps, geysers, air conditioners, etc. (HK-08). Its tasks are its history.
 */
#[Fillable(['name', 'category', 'room_id', 'location', 'serial_number', 'supplier', 'purchased_on', 'warranty_until', 'purchase_cost_cents', 'notes', 'is_active'])]
class Asset extends Model
{
    use LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'purchased_on' => 'date',
            'warranty_until' => 'date',
            'purchase_cost_cents' => MoneyCast::class,
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('maintenance')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function isUnderWarranty(): bool
    {
        return $this->warranty_until !== null && $this->warranty_until->endOfDay()->isFuture();
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->latest('id');
    }
}
