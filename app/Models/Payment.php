<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * One payment in the ledger (PAY-01). Status changes go through PaymentLedger.
 */
#[Fillable([
    'booking_id', 'invoice_id', 'method', 'status', 'amount_cents', 'refunded_cents', 'gateway', 'gateway_reference',
    'bank_reference', 'payer_reference', 'notes', 'exception_reason', 'recorded_by', 'verified_by', 'verified_at', 'received_at',
])]
class Payment extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity;

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentRecordStatus::class,
            'amount_cents' => MoneyCast::class,
            'refunded_cents' => MoneyCast::class,
            'verified_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('payments')
            ->logOnly(['status', 'amount_cents', 'refunded_cents', 'bank_reference', 'verified_by', 'exception_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Proof-of-payment uploads are private: never on the public disk.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('proof')
            ->useDisk(config('filesystems.default'))
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png']);
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
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return HasOne<Receipt, $this>
     */
    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function refundableAmount(): Money
    {
        return $this->amount_cents->subtract($this->refunded_cents);
    }
}
