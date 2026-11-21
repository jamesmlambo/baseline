<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Support\Money;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A reservation. Status changes go through BookingStateMachine only, never direct updates.
 */
#[Fillable([
    'reference', 'public_token', 'status', 'payment_status', 'source', 'external_reference', 'guest_id',
    'arrival_date', 'departure_date', 'lead_guest_name', 'lead_guest_email', 'lead_guest_phone',
    'hold_expires_at', 'total_cents', 'deposit_due_cents', 'preferred_payment_method', 'special_requests',
    'terms_accepted_at', 'created_by', 'reception_code', 'checked_in_at', 'checked_in_by', 'checked_out_at', 'checked_out_by',
    'portal_revoked_at',
])]
#[Hidden(['public_token', 'reception_code'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'attribution' => 'array',
            // SEC-07: contact details encrypted at rest, like the guest profile.
            'lead_guest_email' => 'encrypted',
            'lead_guest_phone' => 'encrypted',
            'source' => BookingSource::class,
            'arrival_date' => 'immutable_date',
            'departure_date' => 'immutable_date',
            'hold_expires_at' => 'immutable_datetime',
            'total_cents' => MoneyCast::class,
            'deposit_due_cents' => MoneyCast::class,
            'preferred_payment_method' => PaymentMethod::class,
            'terms_accepted_at' => 'immutable_datetime',
            'checked_in_at' => 'immutable_datetime',
            'checked_out_at' => 'immutable_datetime',
            'portal_revoked_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<AuthorizedPhone, $this>
     */
    public function authorizedPhones(): HasMany
    {
        return $this->hasMany(AuthorizedPhone::class);
    }

    /**
     * @return HasMany<GuestRequest, $this>
     */
    public function guestRequests(): HasMany
    {
        return $this->hasMany(GuestRequest::class)->latest('id');
    }

    /**
     * @return HasMany<GuestMessage, $this>
     */
    public function guestMessages(): HasMany
    {
        return $this->hasMany(GuestMessage::class)->orderBy('id');
    }

    /**
     * @return HasOne<Review, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * Total of all issued, non-voided invoices (the stay, extensions and added charges).
     */
    public function invoicedTotal(): Money
    {
        return Money::cents((int) $this->invoices()->whereNull('voided_at')->sum('total_cents'));
    }

    /**
     * Credit notes that reduce what is owed (refund credit notes are excluded: that money was paid and returned).
     */
    public function creditedTotal(): Money
    {
        return Money::cents((int) CreditNote::query()
            ->whereIn('invoice_id', $this->invoices()->select('id'))
            ->whereNull('refund_id')
            ->sum('total_cents'));
    }

    /**
     * Verified money received, minus refunds.
     */
    public function paidTotal(): Money
    {
        $succeeded = $this->payments()->where('status', PaymentRecordStatus::Succeeded);

        return Money::cents((int) (clone $succeeded)->sum('amount_cents') - (int) (clone $succeeded)->sum('refunded_cents'));
    }

    public function owedTotal(): Money
    {
        return $this->invoicedTotal()->subtract($this->creditedTotal());
    }

    public function balanceDue(): Money
    {
        return $this->owedTotal()->subtract($this->paidTotal());
    }

    /**
     * @return HasOne<GuestRegisterEntry, $this>
     */
    public function registerEntry(): HasOne
    {
        return $this->hasOne(GuestRegisterEntry::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasMany<FrontDeskLog, $this>
     */
    public function frontDeskLogs(): HasMany
    {
        return $this->hasMany(FrontDeskLog::class)->latest('id');
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
                // Contact details are deliberately not copied into the audit log (PRIV-01).
                'status', 'payment_status', 'arrival_date', 'departure_date',
                'lead_guest_name', 'hold_expires_at', 'total_cents',
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
