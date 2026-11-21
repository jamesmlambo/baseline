<?php

declare(strict_types=1);

namespace App\Actions\Housekeeping;

use App\Models\MaintenanceSchedule;
use App\Models\Task;
use App\Services\Housekeeping\TaskManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * HK-08: preventive maintenance. Each due schedule creates one task for its due date, then moves
 * on to the next date. Missed runs catch up with a single task, not one per missed period.
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

                    $task = $this->tasks->createOnce("schedule:{$schedule->id}:{$dueOn->toDateString()}", $schedule->task_type, Task::SOURCE_SCHEDULE, [
                        'title' => $schedule->title,
                        'room_id' => $schedule->room_id,
                        'asset_id' => $schedule->asset_id,
                        'location' => $schedule->location,
                        'assigned_to' => $schedule->assigned_to,
                        'maintenance_schedule_id' => $schedule->id,
                        'checklist' => $schedule->checklist ?? [],
                        'due_at' => $dueOn->endOfDay(),
                    ]);

                    $created += $task->wasRecentlyCreated ? 1 : 0;

                    $next = $dueOn;
                    while ($next->lessThanOrEqualTo($today)) {
                        $next = $next->addDays($schedule->interval_days);
                    }

                    $schedule->forceFill(['next_due_on' => $next->toDateString()])->save();
                });
            });

        return $created;
    }
}
