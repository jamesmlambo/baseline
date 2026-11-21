<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One delivery of a daily report to one person by email or push, with attempts and the last error (REP-03).
 * Status: pending, sent, failed (will be retried), skipped (channel not connected).
 */
#[Fillable(['daily_report_id', 'user_id', 'channel', 'status'])]
class ReportDelivery extends Model
{
    public const MAX_ATTEMPTS = 5;

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DailyReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class, 'daily_report_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
