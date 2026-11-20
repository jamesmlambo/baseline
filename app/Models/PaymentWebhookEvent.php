<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Every gateway webhook, stored once by (gateway, event_id) so duplicates are ignored (PAY-06).
 */
#[Fillable(['gateway', 'event_id', 'event_type', 'payload', 'payment_id', 'occurred_at', 'processed_at', 'outcome'])]
class PaymentWebhookEvent extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
