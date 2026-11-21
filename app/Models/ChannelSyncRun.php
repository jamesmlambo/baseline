<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sync attempt (CHN-04): ok, failed (with the error) or not_connected (null driver).
 */
#[Fillable(['channel_id', 'ical_link_id', 'operation', 'status', 'items', 'message', 'started_at', 'finished_at'])]
class ChannelSyncRun extends Model
{
    protected function casts(): array
    {
        return [
            'items' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
