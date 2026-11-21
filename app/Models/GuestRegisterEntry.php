<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Guest register captured at check-in (REC-04). Which fields the law requires is still to be
 * verified (COMPLIANCE_TODO), so required fields are a setting. ID numbers and addresses are encrypted.
 */
#[Fillable(['booking_id', 'guest_id', 'full_name', 'id_type', 'id_number', 'nationality', 'home_address', 'vehicle_registration', 'number_of_guests', 'recorded_by'])]
#[Hidden(['id_number', 'home_address'])]
class GuestRegisterEntry extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'id_number' => 'encrypted',
            'home_address' => 'encrypted',
            'number_of_guests' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Who changed the register and when — not the sensitive values themselves.
        return LogOptions::defaults()
            ->useLogName('guest_register')
            ->logOnly(['full_name', 'id_type', 'nationality', 'vehicle_registration', 'number_of_guests'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function maskedIdNumber(): ?string
    {
        $id = $this->id_number;

        return $id === null || $id === '' ? null : str_repeat('•', max(0, strlen($id) - 4)).substr($id, -4);
    }
}
