<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['inspection_id', 'criterion', 'score', 'passed', 'comment'])]
class InspectionScore extends Model
{
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'passed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Inspection, $this>
     */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }
}
