<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FrontDeskLogType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One front-desk event: key handover/return/loss, items issued, complaint or note (REC-05).
 */
#[Fillable(['booking_id', 'room_id', 'type', 'item', 'quantity', 'details', 'recorded_by', 'resolved_at', 'resolved_by'])]
class FrontDeskLog extends Model
{
    protected function casts(): array
    {
        return [
            'type' => FrontDeskLogType::class,
            'quantity' => 'integer',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  Builder<FrontDeskLog>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('type', [FrontDeskLogType::Complaint, FrontDeskLogType::KeyLost])->whereNull('resolved_at');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
