<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PunchSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One import run (CSV file or device fetch), with counts and row errors (ATT-02).
 */
#[Fillable(['source', 'filename', 'rows_read', 'rows_added', 'rows_duplicate', 'rows_failed', 'errors', 'user_id'])]
class AttendanceImport extends Model
{
    protected function casts(): array
    {
        return [
            'source' => PunchSource::class,
            'errors' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
