<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\IsIssuedDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A quotation is not an invoice (PAY-01). It can be converted into one without retyping (PAY-04).
 */
#[Fillable([
    'number', 'booking_id', 'guest_id', 'recipient_name', 'subtotal_cents', 'tax_cents', 'total_cents',
    'valid_until', 'converted_invoice_id', 'issued_by', 'issued_at',
])]
class Quotation extends Model
{
    use IsIssuedDocument;

    protected function casts(): array
    {
        return [
            'subtotal_cents' => MoneyCast::class,
            'tax_cents' => MoneyCast::class,
            'total_cents' => MoneyCast::class,
            'valid_until' => 'immutable_date',
            'issued_at' => 'immutable_datetime',
        ];
    }

    protected function mutableAfterIssue(): array
    {
        return ['converted_invoice_id', 'updated_at'];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }
}
