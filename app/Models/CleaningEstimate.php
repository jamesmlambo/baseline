<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How long a task type usually takes in a room type (HK-07), used to balance rosters (M9).
 */
#[Fillable(['accommodation_type_id', 'task_type', 'minutes'])]
class CleaningEstimate extends Model
{
    protected function casts(): array
    {
        return [
            'task_type' => TaskType::class,
            'minutes' => 'integer',
        ];
    }

    public static function minutesFor(?Room $room, TaskType $type): ?int
    {
        if ($room === null) {
            return null;
        }

        return static::query()
            ->where('accommodation_type_id', $room->accommodation_type_id)
            ->where('task_type', $type)
            ->value('minutes');
    }

    /**
     * @return BelongsTo<AccommodationType, $this>
     */
    public function accommodationType(): BelongsTo
    {
        return $this->belongsTo(AccommodationType::class);
    }
}
