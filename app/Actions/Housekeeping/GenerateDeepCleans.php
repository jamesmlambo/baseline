<?php

declare(strict_types=1);

namespace App\Actions\Housekeeping;

use App\Enums\TaskType;
use App\Models\Room;
use App\Models\Task;
use App\Services\Housekeeping\TaskManager;
use Carbon\CarbonImmutable;

/**
 * HK-04: one Monday deep clean per active room, with the deep-clean checklist. Safe to run more
 * than once on the same day.
 */
class GenerateDeepCleans
{
    public function __construct(private readonly TaskManager $tasks) {}

    public function handle(?CarbonImmutable $day = null): int
    {
        $day ??= CarbonImmutable::now('Africa/Johannesburg');

        if (! $day->isMonday()) {
            return 0;
        }

        $created = 0;

        Room::query()->active()->orderBy('sort_order')->each(function (Room $room) use ($day, &$created): void {
            $task = $this->tasks->createOnce("deep_clean:{$room->id}:{$day->toDateString()}", TaskType::DeepClean, Task::SOURCE_DEEP_CLEAN, [
                'room_id' => $room->id,
                'title' => "Monday deep clean — {$room->name}",
                'due_at' => $day->endOfDay(),
            ]);

            $created += $task->wasRecentlyCreated ? 1 : 0;
        });

        return $created;
    }
}
