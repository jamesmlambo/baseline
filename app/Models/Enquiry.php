<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Contact-form messages and website booking requests (WEB-10). A booking request is not a
 * reservation: it holds no inventory until reception turns it into a booking (online booking: M4).
 */
#[Fillable([
    'type', 'status', 'name', 'email', 'phone', 'message', 'accommodation_type_id', 'arrival_date', 'departure_date',
    'adults', 'children', 'quoted_total_cents', 'marketing_consent', 'handled_by', 'handled_at', 'ip_hash',
])]
class Enquiry extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'type' => EnquiryType::class,
            'status' => EnquiryStatus::class,
            'attribution' => 'array',
            // SEC-07: contact details encrypted at rest.
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'arrival_date' => 'immutable_date',
            'departure_date' => 'immutable_date',
            'quoted_total_cents' => MoneyCast::class,
            'marketing_consent' => 'boolean',
            'handled_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('enquiries')->logOnly(['status', 'handled_by'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<AccommodationType, $this>
     */
    public function accommodationType(): BelongsTo
    {
        return $this->belongsTo(AccommodationType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
