<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A therapist or room held for a booking. The database refuses two unreleased holds that overlap (LSR-03).
 */
#[Fillable(['service_booking_id', 'service_resource_id', 'starts_at', 'ends_at', 'released_at'])]
class ServiceBookingResource extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<ServiceResource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(ServiceResource::class, 'service_resource_id');
    }
}
