<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The stored 17:00 snapshot for one day (REP-03), kept as an archive (REP-04). The optional
 * day-close freeze records the reconciled money totals later in the day; neither is ever edited.
 */
#[Fillable(['report_date', 'data', 'generated_at'])]
class DailyReport extends Model
{
    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'data' => 'array',
            'generated_at' => 'datetime',
            'frozen_totals' => 'array',
            'frozen_at' => 'datetime',
        ];
    }

    public function isFrozen(): bool
    {
        return $this->frozen_at !== null;
    }

    /**
     * @return HasMany<ReportDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(ReportDelivery::class);
    }
}
