<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A reservation. Status changes go through BookingStateMachine only, never direct updates.
 */
#[Fillable([
    'reference', 'public_token', 'status', 'payment_status', 'source', 'external_reference', 'guest_id',
    'arrival_date', 'departure_date', 'lead_guest_name', 'lead_guest_email', 'lead_guest_phone',
    'hold_expires_at', 'total_cents', 'deposit_due_cents', 'preferred_payment_method', 'special_requests',
    'terms_accepted_at', 'created_by',
])]
#[Hidden(['public_token'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'source' => BookingSource::class,
            'arrival_date' => 'immutable_date',
            'departure_date' => 'immutable_date',
            'hold_expires_at' => 'immutable_datetime',
            'total_cents' => MoneyCast::class,
            'deposit_due_cents' => MoneyCast::class,
            'preferred_payment_method' => PaymentMethod::class,
            'terms_accepted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /**
     * @return HasMany<BookingExtra, $this>
     */
    public function extras(): HasMany
    {
        return $this->hasMany(BookingExtra::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderBy('id');
    }

    /**
     * The booking's current (latest, not voided) invoice.
     */
    public function currentInvoice(): ?Invoice
    {
        return $this->invoices()->whereNull('voided_at')->latest('id')->first();
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /**
     * @return HasMany<Receipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class)->orderBy('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('bookings')
            ->logOnly([
                'status', 'payment_status', 'arrival_date', 'departure_date',
                'lead_guest_name', 'lead_guest_email', 'lead_guest_phone', 'hold_expires_at', 'total_cents',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return HasMany<BookingRoom, $this>
     */
    public function bookingRooms(): HasMany
    {
        return $this->hasMany(BookingRoom::class);
    }

    /**
     * @return HasMany<BookingRoom, $this>
     */
    public function activeBookingRooms(): HasMany
    {
        return $this->bookingRooms()->whereNull('released_at');
    }

    /**
     * @return HasMany<BookingStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)->with('changedBy')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function nights(): int
    {
        return (int) $this->arrival_date->diffInDays($this->departure_date);
    }
}
