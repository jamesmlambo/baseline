<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\ServiceBookingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A booking for a leisure service (LSR-01..05). Charged to the guest's room when they are in
 * house, otherwise paid at reception.
 */
#[Fillable([
    'reference', 'service_id', 'starts_at', 'ends_at', 'party_size', 'customer_name', 'customer_phone', 'customer_email',
    'booking_id', 'status', 'total_cents', 'charge_to_room', 'indemnity_accepted_at', 'notes', 'source', 'created_by',
])]
class ServiceBooking extends Model
{
    use LogsActivity;

    public const SOURCE_RECEPTION = 'reception';

    public const SOURCE_PORTAL = 'portal';

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'status' => ServiceBookingStatus::class,
            'total_cents' => MoneyCast::class,
            'customer_phone' => 'encrypted',
            'customer_email' => 'encrypted',
            'charge_to_room' => 'boolean',
            'indemnity_accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('leisure')
            ->logOnly(['status', 'starts_at', 'party_size', 'total_cents', 'charge_to_room', 'invoice_id', 'payment_id', 'cancel_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function isPaidOrBilled(): bool
    {
        return $this->invoice_id !== null;
    }

    /**
     * @param  Builder<ServiceBooking>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', ServiceBookingStatus::Confirmed);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function stay(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return HasMany<ServiceBookingResource, $this>
     */
    public function resourceAllocations(): HasMany
    {
        return $this->hasMany(ServiceBookingResource::class);
    }

    /**
     * @return HasOne<KitchenTicket, $this>
     */
    public function kitchenTicket(): HasOne
    {
        return $this->hasOne(KitchenTicket::class);
    }
}
