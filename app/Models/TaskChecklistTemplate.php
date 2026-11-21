<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The default checklist for a task type (HK-02, HK-04). Copied onto each new task.
 */
#[Fillable(['task_type', 'items'])]
class TaskChecklistTemplate extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'task_type' => TaskType::class,
            'items' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('settings')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return list<array{label: string, done: bool}>
     */
    public static function checklistFor(TaskType $type): array
    {
        $items = static::query()->where('task_type', $type)->value('items');

        return array_values(array_map(
            fn (mixed $label): array => ['label' => (string) $label, 'done' => false],
            array_filter(is_array($items) ? $items : (json_decode((string) $items, true) ?: []), fn (mixed $label): bool => trim((string) $label) !== ''),
        ));
    }
}
