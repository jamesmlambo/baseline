<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\MessageChannel;
use App\Enums\MessageClassification;
use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One SMS or WhatsApp message (MSG-05): what was sent, to whom (encrypted), its status, retries and cost.
 */
#[Fillable([
    'message_template_id', 'template_version', 'channel', 'classification', 'recipient', 'recipient_hash', 'body', 'status',
    'skip_reason', 'idempotency_key', 'booking_id', 'guest_id', 'provider', 'provider_message_id', 'attempts', 'last_error',
    'cost_cents', 'sent_by', 'sent_at', 'delivered_at',
])]
#[Hidden(['recipient_hash'])]
class Message extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'classification' => MessageClassification::class,
            'status' => MessageStatus::class,
            'recipient' => 'encrypted',
            'cost_cents' => MoneyCast::class,
            'attempts' => 'integer',
            'sent_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<MessageTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function maskedRecipient(): string
    {
        $recipient = (string) $this->recipient;

        return strlen($recipient) > 4 ? substr($recipient, 0, 3).str_repeat('•', strlen($recipient) - 6).substr($recipient, -3) : $recipient;
    }
}
