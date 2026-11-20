<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\IsIssuedDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The only way to correct an issued invoice (PAY-03). Also issued for every processed refund.
 */
#[Fillable(['number', 'invoice_id', 'refund_id', 'booking_id', 'recipient_name', 'reason', 'total_cents', 'issued_by', 'issued_at'])]
class CreditNote extends Model
{
    use IsIssuedDocument;

    protected function casts(): array
    {
        return [
            'total_cents' => MoneyCast::class,
            'issued_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Refund, $this>
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }
}
