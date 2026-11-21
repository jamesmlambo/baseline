<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message between an in-house guest and reception (GST-07 reception inbox).
 * direction: "in" from the guest, "out" from staff.
 */
#[Fillable(['booking_id', 'direction', 'body', 'staff_id', 'read_at', 'escalation_level'])]
class GuestMessage extends Model
{
    public const IN = 'in';

    public const OUT = 'out';

    protected function casts(): array
    {
        return [
            'read_at' => 'immutable_datetime',
            'escalation_level' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
