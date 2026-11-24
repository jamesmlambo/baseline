<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\HasEmailHistory;
use App\Models\Concerns\IsIssuedDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A quotation is not an invoice (PAY-01). It can be converted into one without retyping (PAY-04).
 */
#[Fillable([
    'number', 'booking_id', 'guest_id', 'recipient_name', 'recipient_email', 'recipient_phone', 'subtotal_cents', 'tax_cents', 'total_cents',
    'valid_until', 'notes', 'items', 'public_token', 'converted_invoice_id', 'issued_by', 'issued_at',
])]
#[Hidden(['public_token'])]
class Quotation extends Model
{
    use HasEmailHistory, IsIssuedDocument;

    /**
     * The customer's own link to this quotation (PDF). The QR code on the quotation points here.
     */
    public function publicUrl(): ?string
    {
        return $this->public_token ? route('documents.show', ['quotation', $this->public_token]) : null;
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->endOfDay()->isPast();
    }

    /**
     * A quotation made in the Quotations section with rooms, not yet booked or expired.
     */
    public function canBeBooked(): bool
    {
        return $this->booking_id === null
            && $this->converted_invoice_id === null
            && ! $this->isExpired()
            && ($this->items['rooms'] ?? []) !== [];
    }

    /**
     * Shown in the admin: Booked, Converted (to an invoice), Expired or Valid.
     */
    public function statusLabel(): string
    {
        return match (true) {
            $this->booking_id !== null && $this->items !== null => 'Booked',
            $this->converted_invoice_id !== null => 'Converted',
            $this->isExpired() => 'Expired',
            default => 'Valid',
        };
    }

    /**
     * Email for sending: the one given on the quote, or the booking's.
     */
    public function contactEmail(): ?string
    {
        return $this->recipient_email ?: $this->booking?->lead_guest_email;
    }

    public function contactPhone(): ?string
    {
        return $this->recipient_phone ?: $this->booking?->lead_guest_phone;
    }

    protected function casts(): array
    {
        return [
            'recipient_email' => 'encrypted',
            'recipient_phone' => 'encrypted',
            'items' => 'array',
            'subtotal_cents' => MoneyCast::class,
            'tax_cents' => MoneyCast::class,
            'total_cents' => MoneyCast::class,
            'valid_until' => 'immutable_date',
            'issued_at' => 'immutable_datetime',
        ];
    }

    protected function mutableAfterIssue(): array
    {
        return ['converted_invoice_id', 'booking_id', 'updated_at'];
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
