<?php

declare(strict_types=1);

namespace App\Actions\Housekeeping;

use App\Models\MaintenanceSchedule;
use App\Models\Task;
use App\Services\Housekeeping\TaskManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * HK-08: recurring tasks (preventive maintenance and any repeating job). Each due schedule creates
 * one task for its due date, then moves on to the next day, week or month. Missed runs catch up
 * with a single task, not one per missed period. A schedule past its end date switches itself off.
 */
class RunMaintenanceSchedules
{
    public function __construct(private readonly TaskManager $tasks) {}

    public function handle(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::now('Africa/Johannesburg')->startOfDay();
        $created = 0;

        MaintenanceSchedule::query()
            ->where('is_active', true)
            ->whereDate('next_due_on', '<=', $today->toDateString())
            ->pluck('id')
            ->each(function (int $id) use ($today, &$created): void {
                DB::transaction(function () use ($id, $today, &$created): void {
                    /** @var MaintenanceSchedule|null $schedule */
                    $schedule = MaintenanceSchedule::query()->whereKey($id)->lockForUpdate()->first();

                    if ($schedule === null || $schedule->next_due_on->toDateString() > $today->toDateString()) {
                        return;
                    }

                    $dueOn = CarbonImmutable::parse($schedule->next_due_on->toDateString(), 'Africa/Johannesburg');

                    if ($schedule->ends_on === null || $dueOn->toDateString() <= $schedule->ends_on->toDateString()) {
                        $task = $this->tasks->createOnce("schedule:{$schedule->id}:{$dueOn->toDateString()}", $schedule->task_type, Task::SOURCE_SCHEDULE, array_filter([
                            'title' => $schedule->title,
                            'room_id' => $schedule->room_id,
                            'asset_id' => $schedule->asset_id,
                            'location' => $schedule->location,
                            'assigned_to' => $schedule->assigned_to,
                            'maintenance_schedule_id' => $schedule->id,
                            'checklist' => $schedule->checklist ?? [],
                            'priority' => $schedule->priority,
                            'notes' => $schedule->notes,
                            'due_at' => $schedule->dueAtOn($dueOn),
                        ], fn ($value): bool => $value !== null));

                        $created += $task->wasRecentlyCreated ? 1 : 0;
                    }

                    // Missed runs catch up with one task: the next date is the first one after today.
                    $next = $schedule->nextOccurrenceAfter($today);

                    $schedule->forceFill($next === null
                        ? ['is_active' => false]
                        : ['next_due_on' => $next->toDateString()])->save();
                });
            });

        return $created;
    }
}
