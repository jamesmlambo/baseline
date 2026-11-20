<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A refund request. Needs a second, different authorised person to approve (DEC-06).
 */
#[Fillable([
    'payment_id', 'amount_cents', 'reason', 'status', 'requested_by', 'approved_by', 'approved_at',
    'rejected_by', 'rejection_reason', 'gateway_refund_reference', 'processed_at',
])]
class Refund extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'amount_cents' => MoneyCast::class,
            'status' => RefundStatus::class,
            'approved_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('refunds')
            ->logOnly(['amount_cents', 'reason', 'status', 'requested_by', 'approved_by', 'rejected_by', 'rejection_reason', 'gateway_refund_reference'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasOne<CreditNote, $this>
     */
    public function creditNote(): HasOne
    {
        return $this->hasOne(CreditNote::class);
    }
}
