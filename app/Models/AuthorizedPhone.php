<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An extra phone number the verified booker allowed to use the guest portal (GST-04).
 */
#[Fillable(['booking_id', 'label', 'phone', 'phone_hash', 'added_by', 'revoked_at'])]
#[Hidden(['phone_hash'])]
class AuthorizedPhone extends Model
{
    use LogsActivity;

    protected $table = 'booking_authorized_phones';

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('guest_portal')->logOnly(['booking_id', 'label', 'added_by', 'revoked_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @param  Builder<AuthorizedPhone>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function maskedPhone(): string
    {
        $phone = (string) $this->phone;

        return substr($phone, 0, 3).str_repeat('•', max(0, strlen($phone) - 6)).substr($phone, -3);
    }
}
