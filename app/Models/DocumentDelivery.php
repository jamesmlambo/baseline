<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One email of an invoice, quotation or receipt (PAY-02): the address it went to (encrypted),
 * when, who sent it, and why (sent by staff, or automatically with a booking confirmation).
 */
#[Fillable(['document_type', 'document_id', 'channel', 'recipient', 'reason', 'sent_by', 'sent_at'])]
class DocumentDelivery extends Model
{
    public const CHANNEL_EMAIL = 'email';

    public const REASON_STAFF = 'staff';

    public const REASON_BOOKING_CONFIRMED = 'booking_confirmed';

    protected function casts(): array
    {
        return [
            'recipient' => 'encrypted',
            'sent_at' => 'immutable_datetime',
        ];
    }

    public function reasonLabel(): string
    {
        return match ($this->reason) {
            self::REASON_BOOKING_CONFIRMED => 'Automatic, booking confirmed',
            default => 'Sent by staff',
        };
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function document(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
