<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\IsIssuedDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proof that a verified payment was received. One receipt per payment.
 */
#[Fillable(['number', 'payment_id', 'invoice_id', 'booking_id', 'recipient_name', 'amount_cents', 'public_token', 'issued_at'])]
class Receipt extends Model
{
    use IsIssuedDocument;

    protected function casts(): array
    {
        return [
            'amount_cents' => MoneyCast::class,
            'issued_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
