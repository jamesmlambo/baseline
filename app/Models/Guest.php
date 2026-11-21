<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageChannel;
use Database\Factories\GuestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Guest profile reused across bookings (BOOK-06). Email and phone are encrypted at rest and
 * found through keyed lookup hashes of their normalised values (SEC-07).
 */
#[Fillable(['first_name', 'last_name', 'email', 'phone', 'notes', 'marketing_consent', 'marketing_consent_at', 'channel_opt_outs'])]
#[Hidden(['email_hash', 'phone_hash'])]
class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'legal_hold_at' => 'datetime',
            'anonymised_at' => 'datetime',
            'marketing_consent' => 'boolean',
            'marketing_consent_at' => 'immutable_datetime',
            'channel_opt_outs' => 'array',
        ];
    }

    /**
     * MSG-04: the guest asked not to be messaged on this channel (SMS or WhatsApp).
     */
    public function hasOptedOutOf(MessageChannel $channel): bool
    {
        return in_array($channel->value, $this->channel_opt_outs ?? [], true);
    }

    protected static function booted(): void
    {
        static::saving(function (Guest $guest): void {
            $guest->email_hash = self::lookupHash('email', $guest->email);
            $guest->phone_hash = self::lookupHash('phone', $guest->phone);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Encrypted contact details are deliberately not copied into the audit log in clear text.
        return LogOptions::defaults()
            ->useLogName('guests')
            ->logOnly(['first_name', 'last_name', 'email_hash', 'phone_hash', 'marketing_consent'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public static function normaliseEmail(?string $email): ?string
    {
        $email = $email !== null ? strtolower(trim($email)) : null;

        return $email === '' ? null : $email;
    }

    /**
     * South African numbers are stored in international form: 082 123 4567 → +27821234567.
     */
    public static function normalisePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '27'.substr($digits, 1);
        }

        return '+'.$digits;
    }

    public static function lookupHash(string $field, ?string $value): ?string
    {
        $normalised = $field === 'email' ? self::normaliseEmail($value) : self::normalisePhone($value);

        return $normalised === null ? null : hash_hmac('sha256', $field.':'.$normalised, (string) config('app.key'));
    }

    /**
     * @param  Builder<Guest>  $query
     */
    public function scopeMatchingContact(Builder $query, ?string $email, ?string $phone): void
    {
        $emailHash = self::lookupHash('email', $email);
        $phoneHash = self::lookupHash('phone', $phone);

        $query->where(function (Builder $query) use ($emailHash, $phoneHash): void {
            if ($emailHash !== null) {
                $query->orWhere('email_hash', $emailHash);
            }

            if ($phoneHash !== null) {
                $query->orWhere('phone_hash', $phoneHash);
            }

            if ($emailHash === null && $phoneHash === null) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
