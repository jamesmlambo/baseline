<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PaymentRecordStatus;
use App\Models\Concerns\IsIssuedDocument;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An issued invoice. Immutable once issued (PAY-03); balance comes from the payments ledger.
 */
#[Fillable([
    'number', 'booking_id', 'guest_id', 'quotation_id', 'recipient_name', 'subtotal_cents', 'tax_cents',
    'total_cents', 'due_on', 'public_token', 'issued_by', 'issued_at', 'voided_at',
])]
class Invoice extends Model
{
    use IsIssuedDocument;

    protected function casts(): array
    {
        return [
            'subtotal_cents' => MoneyCast::class,
            'tax_cents' => MoneyCast::class,
            'total_cents' => MoneyCast::class,
            'due_on' => 'immutable_date',
            'issued_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
        ];
    }

    protected function mutableAfterIssue(): array
    {
        return ['voided_at', 'updated_at'];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<CreditNote, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    /**
     * Verified money received, minus refunds.
     */
    public function amountPaid(): Money
    {
        $received = (int) $this->payments()->where('status', PaymentRecordStatus::Succeeded)->sum('amount_cents');
        $refunded = (int) $this->payments()->where('status', PaymentRecordStatus::Succeeded)->sum('refunded_cents');

        return Money::cents($received - $refunded);
    }

    /**
     * Total of credit notes that reduce what is owed (not those issued for refunds of money already paid).
     */
    public function creditedAmount(): Money
    {
        return Money::cents((int) $this->creditNotes()->whereNull('refund_id')->sum('total_cents'));
    }

    public function balanceDue(): Money
    {
        return $this->total_cents->subtract($this->creditedAmount())->subtract($this->amountPaid());
    }

    public function isPaid(): bool
    {
        return $this->balanceDue()->cents <= 0;
    }
}
