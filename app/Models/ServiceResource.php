<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceResourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A therapist, treatment room or bookable area (LSR-03).
 */
#[Fillable(['name', 'type', 'employee_id', 'is_active', 'is_demo'])]
class ServiceResource extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true, 'is_demo' => false];

    protected function casts(): array
    {
        return [
            'type' => ServiceResourceType::class,
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ServiceBookingResource, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ServiceBookingResource::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
