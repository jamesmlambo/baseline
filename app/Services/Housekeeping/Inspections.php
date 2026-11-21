<?php

declare(strict_types=1);

namespace App\Services\Housekeeping;

use App\Enums\InspectionStatus;
use App\Enums\RoomCleaningStatus;
use App\Enums\TaskPriority;
use App\Models\Inspection;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\StaffAlert;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Inspections, rework and room readiness (HK-05, HK-06).
 *
 * - Every criterion is scored 0..scale_max. Any criterion below the pass mark fails the inspection.
 * - A failure sends the room back to Dirty, creates a rework task for the same worker with only the
 *   failed items, and tells them. Finishing the rework opens a new inspection (re-inspection).
 * - A pass makes the room Ready. The owner can also mark a room Ready with a reason (PEND-06).
 */
class Inspections
{
    public function openFor(Task $task): Inspection
    {
        $scaleMax = max(1, (int) Setting::get('inspections.scale_max', 5));
        $passMark = min($scaleMax, max(1, (int) Setting::get('inspections.pass_mark', 4)));

        return Inspection::query()->firstOrCreate(
            ['task_id' => $task->id],
            ['room_id' => $task->room_id, 'status' => InspectionStatus::Pending, 'scale_max' => $scaleMax, 'pass_mark' => $passMark],
        );
    }

    /**
     * What the inspector scores: the task's checklist, or one overall score.
     *
     * @return list<string>
     */
    public function criteriaFor(Inspection $inspection): array
    {
        $labels = array_column($inspection->task->checklistItems(), 'label');

        return $labels !== [] ? $labels : ['Overall'];
    }

    /**
     * @param  array<string, array{score: int|string, comment?: ?string}>  $scores  keyed by criterion
     */
    public function record(Inspection $inspection, User $inspector, array $scores, ?string $notes = null): Inspection
    {
        if (! $inspector->can('inspect', $inspection)) {
            throw new AuthorizationException('You cannot score this inspection. Inspectors may not inspect their own cleaning.');
        }

        $criteria = $this->criteriaFor($inspection);
        $rows = [];

        foreach ($criteria as $criterion) {
            $raw = $scores[$criterion]['score'] ?? null;

            if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                throw new InvalidArgumentException("Give a score for “{$criterion}”.");
            }

            $score = (int) $raw;

            if ($score < 0 || $score > $inspection->scale_max) {
                throw new InvalidArgumentException("Scores go from 0 to {$inspection->scale_max}.");
            }

            $rows[] = [
                'criterion' => $criterion,
                'score' => $score,
                'passed' => $score >= $inspection->pass_mark,
                'comment' => filled($scores[$criterion]['comment'] ?? null) ? trim((string) $scores[$criterion]['comment']) : null,
            ];
        }

        $failed = array_values(array_filter($rows, fn (array $row): bool => ! $row['passed']));

        $rework = DB::transaction(function () use ($inspection, $inspector, $rows, $failed, $notes): ?Task {
            /** @var Inspection $locked */
            $locked = Inspection::query()->whereKey($inspection->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== InspectionStatus::Pending) {
                throw new InvalidArgumentException('This inspection has already been recorded.');
            }

            $inspection->scores()->createMany($rows);
            $inspection->forceFill([
                'status' => $failed === [] ? InspectionStatus::Passed : InspectionStatus::Failed,
                'inspector_id' => $inspector->id,
                'inspected_at' => now(),
                'notes' => filled($notes) ? trim((string) $notes) : null,
            ])->save();

            $task = $inspection->task;
            $room = $inspection->room;

            if ($failed === []) {
                if ($room !== null) {
                    TaskManager::setRoomStatus($room, RoomCleaningStatus::Ready, $inspector, 'Inspection passed');
                }

                return null;
            }

            if ($room !== null) {
                TaskManager::setRoomStatus($room, RoomCleaningStatus::Dirty, $inspector, 'Inspection failed');
            }

            return app(TaskManager::class)->createOnce("rework:{$inspection->id}", $task->type, Task::SOURCE_REWORK, [
                'title' => "Rework: {$task->title}",
                'room_id' => $task->room_id,
                'location' => $task->location,
                'booking_id' => $task->booking_id,
                'parent_task_id' => $task->id,
                'assigned_to' => $task->completed_by,
                'priority' => TaskPriority::High,
                'checklist' => array_column($failed, 'criterion'),
                'notes' => collect($failed)->map(fn (array $row): string => "{$row['criterion']}: {$row['score']}/{$inspection->scale_max}".($row['comment'] ? " — {$row['comment']}" : ''))->implode("\n"),
            ]);
        });

        if ($rework?->assignee !== null) {
            $rework->assignee->notify(new StaffAlert(
                "Rework needed: {$rework->locationLabel()}",
                count($failed).' item(s) did not pass inspection. The room will be inspected again when you finish.',
                url('/staff/my-tasks'),
            ));
        }

        return $inspection->refresh();
    }

    /**
     * HK-06 / PEND-06: mark a room Ready without a passed inspection. Owner only, audited with the reason.
     */
    public function overrideReady(Room $room, User $by, string $reason): void
    {
        if (! $by->can('overrideReadiness', $room)) {
            throw new AuthorizationException('Only the owner can mark a room ready without an inspection.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Give a reason for the override.');
        }

        TaskManager::setRoomStatus($room, RoomCleaningStatus::Ready, $by, 'Owner override: '.trim($reason));

        activity('housekeeping')
            ->performedOn($room)
            ->causedBy($by)
            ->event('readiness_override')
            ->withProperties(['reason' => trim($reason)])
            ->log("Room {$room->name} marked ready without inspection (owner override)");
    }
}
