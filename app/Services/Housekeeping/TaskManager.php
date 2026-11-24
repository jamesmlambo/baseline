<?php

declare(strict_types=1);

namespace App\Services\Housekeeping;

use App\Enums\RepeatUnit;
use App\Enums\RoomCleaningStatus;
use App\Enums\StaffRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\CleaningEstimate;
use App\Models\MaintenanceSchedule;
use App\Models\Room;
use App\Models\Task;
use App\Models\TaskChecklistTemplate;
use App\Models\User;
use App\Notifications\StaffAlert;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Task lifecycle (HK-01..03): create, assign, start, complete with evidence, cancel, report a fault.
 * Finishing a turnover clean sends the room to inspection (HK-05/06).
 */
class TaskManager
{
    public function __construct(private readonly Inspections $inspections) {}

    /**
     * A task created by a member of staff.
     *
     * @param  array{type: TaskType|string, title?: ?string, room_id?: ?int, asset_id?: ?int, location?: ?string, assigned_to?: ?int, priority?: TaskPriority|string|null, due_at?: mixed, notes?: ?string, checklist?: ?array<int, string>}  $data
     */
    public function create(array $data, User $by): Task
    {
        $type = $data['type'] instanceof TaskType ? $data['type'] : TaskType::from($data['type']);

        if (! $by->can('assignType', [Task::class, $type])) {
            throw new AuthorizationException($type->isHousekeeping()
                ? 'Only reception, a manager or the owner can create housekeeping tasks.'
                : 'Only a manager or the owner can create gardening and maintenance tasks.');
        }

        $this->ensureAssignable($data['assigned_to'] ?? null, $type);

        $task = Task::query()->create($this->attributes($type, $data) + [
            'source' => Task::SOURCE_MANUAL,
            'created_by' => $by->id,
            'assigned_by' => ($data['assigned_to'] ?? null) !== null ? $by->id : null,
        ]);

        $this->notifyAssignee($task);

        return $task;
    }

    /**
     * A task that repeats (HK-08): on chosen days of the week, or every N days, weeks or months.
     * The first task is created now for the first due date on or after the start; a recurring
     * schedule creates the next ones on their dates (maintenance:run-schedules).
     *
     * @param  array<string, mixed>  $data  task fields; `due_at` is the start date and the time of day
     * @param  list<int>  $weekdays  ticked days (1 = Monday … 7 = Sunday), with RepeatUnit::Week
     */
    public function createRecurring(array $data, RepeatUnit $unit, int $every, ?string $until, User $by, array $weekdays = []): Task
    {
        if (blank($data['due_at'] ?? null)) {
            throw new InvalidArgumentException('Choose when the task starts, so it can repeat from that date.');
        }

        if ($every < 1 || $every > 365) {
            throw new InvalidArgumentException('Repeat every 1 to 365 days, weeks or months.');
        }

        $weekdays = array_values(array_unique(array_map('intval', $weekdays)));

        if ($weekdays !== [] && ($unit !== RepeatUnit::Week || array_diff($weekdays, range(1, 7)) !== [])) {
            throw new InvalidArgumentException('Days of the week can only be chosen for a weekly task.');
        }

        $start = CarbonImmutable::parse($data['due_at'])->setTimezone('Africa/Johannesburg');

        if ($until !== null && $until < $start->toDateString()) {
            throw new InvalidArgumentException('The end date must be on or after the start date.');
        }

        return DB::transaction(function () use ($data, $unit, $every, $until, $by, $start, $weekdays): Task {
            $probe = new MaintenanceSchedule([
                'repeat_every' => $every,
                'repeat_unit' => $unit,
                'weekdays' => $weekdays !== [] ? $weekdays : null,
                'starts_on' => $start->toDateString(),
                'ends_on' => $until,
            ]);

            // On chosen days, the first task is on the first ticked day from the start date.
            $firstDay = $probe->nextOccurrenceAfter($start->startOfDay()->subDay());

            if ($firstDay === null) {
                throw new InvalidArgumentException('None of the chosen days falls between the start and end dates.');
            }

            $firstDue = $firstDay->setTime($start->hour, $start->minute);
            $task = $this->create(['due_at' => $firstDue] + $data, $by);

            $schedule = new MaintenanceSchedule([
                'title' => $task->title,
                'task_type' => $task->type,
                'asset_id' => $task->asset_id,
                'room_id' => $task->room_id,
                'location' => $task->location,
                'repeat_every' => $every,
                'repeat_unit' => $unit,
                'weekdays' => $weekdays !== [] ? $weekdays : null,
                'starts_on' => $start->toDateString(),
                'next_due_on' => $firstDue->toDateString(),
                'ends_on' => $until,
                'due_time' => $firstDue->format('H:i'),
                'assigned_to' => $task->assigned_to,
                'priority' => $task->priority,
                'checklist' => filled($data['checklist'] ?? null) ? array_values($data['checklist']) : null,
                'notes' => $task->notes,
            ]);

            $next = $schedule->nextOccurrenceAfter($firstDue->startOfDay());
            $schedule->forceFill($next === null ? ['is_active' => false] : ['next_due_on' => $next->toDateString()])->save();

            $task->forceFill(['maintenance_schedule_id' => $schedule->id])->save();

            return $task;
        });
    }

    /**
     * A task created by the system (checkout, Monday deep clean, schedule, rework). The idempotency
     * key makes repeats harmless (CHK-04): the existing task is returned.
     *
     * @param  array<string, mixed>  $data
     */
    public function createOnce(string $idempotencyKey, TaskType $type, string $source, array $data): Task
    {
        $existing = Task::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            $task = DB::transaction(fn (): Task => Task::query()->create($this->attributes($type, $data) + [
                'source' => $source,
                'idempotency_key' => $idempotencyKey,
                'parent_task_id' => $data['parent_task_id'] ?? null,
                'booking_id' => $data['booking_id'] ?? null,
                'maintenance_schedule_id' => $data['maintenance_schedule_id'] ?? null,
            ]));
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }

            return Task::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }

        $task->wasRecentlyCreated = true;
        $this->notifyAssignee($task);

        return $task;
    }

    public function assign(Task $task, ?User $assignee, User $by): Task
    {
        if (! $by->can('assign', $task)) {
            throw new AuthorizationException('You cannot assign this task.');
        }

        $this->ensureAssignable($assignee?->id, $task->type);

        $task->forceFill([
            'assigned_to' => $assignee?->id,
            'assigned_by' => $by->id,
            'status' => $assignee === null ? TaskStatus::Open : $task->status,
            'started_at' => $assignee === null ? null : $task->started_at,
        ])->save();

        $this->notifyAssignee($task);

        return $task;
    }

    public function start(Task $task, User $by): Task
    {
        if (! $by->can('work', $task)) {
            throw new AuthorizationException('Only the person this task is assigned to can start it.');
        }

        if ($task->status === TaskStatus::InProgress) {
            return $task;
        }

        DB::transaction(function () use ($task): void {
            $task->forceFill(['status' => TaskStatus::InProgress, 'started_at' => now()])->save();

            if ($task->room !== null && $task->type->needsInspection()) {
                self::setRoomStatus($task->room, RoomCleaningStatus::Cleaning);
            }
        });

        return $task;
    }

    /**
     * HK-02: completion needs evidence — every checklist item ticked, or a note when there is no checklist.
     *
     * @param  list<string>  $ticked  checklist labels the worker ticked
     * @param  list<UploadedFile|string>  $photos  uploaded files or paths of temporary uploads
     */
    public function complete(Task $task, User $by, array $ticked, ?string $notes = null, array $photos = [], ?Money $cost = null, ?string $supplier = null): Task
    {
        if (! $by->can('work', $task)) {
            throw new AuthorizationException('Only the person this task is assigned to can complete it.');
        }

        $items = $task->checklistItems();
        $notes = filled($notes) ? trim((string) $notes) : null;

        if ($items === [] && $notes === null) {
            throw new InvalidArgumentException('Add a short note about what was done.');
        }

        $missing = array_values(array_diff(array_column($items, 'label'), $ticked));

        if ($missing !== []) {
            throw new InvalidArgumentException('Tick every checklist item first. Still open: '.implode(', ', $missing).'.');
        }

        DB::transaction(function () use ($task, $by, $items, $notes, $cost, $supplier): void {
            /** @var Task $locked */
            $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isOpen()) {
                throw new InvalidArgumentException('This task is already closed.');
            }

            $task->forceFill([
                'status' => TaskStatus::Done,
                'checklist' => array_map(fn (array $item): array => ['label' => $item['label'], 'done' => true], $items),
                'completion_notes' => $notes,
                'started_at' => $task->started_at ?? now(),
                'completed_at' => now(),
                'completed_by' => $by->id,
                'cost_cents' => $cost,
                'supplier' => $supplier,
            ])->save();

            if ($task->room !== null && $task->type->needsInspection()) {
                self::setRoomStatus($task->room, RoomCleaningStatus::AwaitingInspection);
                $this->inspections->openFor($task);
            }
        });

        foreach ($photos as $photo) {
            $adder = $photo instanceof UploadedFile ? $task->addMedia($photo) : $task->addMedia((string) $photo);
            $adder->toMediaCollection('photos', 'local');
        }

        return $task->refresh();
    }

    public function cancel(Task $task, User $by, string $reason): Task
    {
        if (! $by->can('cancel', $task)) {
            throw new AuthorizationException('You cannot cancel this task.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Give a reason.');
        }

        $task->forceFill([
            'status' => TaskStatus::Cancelled,
            'notes' => trim(($task->notes ? $task->notes."\n" : '').'Cancelled: '.$reason),
        ])->save();

        return $task;
    }

    /**
     * HK-08: anyone on the operations team reports a fault; managers and the owner are told.
     */
    public function reportFault(User $by, string $description, ?Room $room = null, ?int $assetId = null, ?string $location = null, TaskPriority $priority = TaskPriority::Normal): Task
    {
        if (! $by->can('reportFault', Task::class)) {
            throw new AuthorizationException('You cannot report faults.');
        }

        if (trim($description) === '') {
            throw new InvalidArgumentException('Describe the fault.');
        }

        if ($room === null && $assetId === null && blank($location)) {
            throw new InvalidArgumentException('Say where the fault is: a room, an item or a place.');
        }

        $task = Task::query()->create($this->attributes(TaskType::Repairs, [
            'title' => 'Fault: '.mb_substr(trim($description), 0, 120),
            'room_id' => $room?->id,
            'asset_id' => $assetId,
            'location' => $location,
            'priority' => $priority,
            'notes' => trim($description),
        ]) + ['source' => Task::SOURCE_FAULT, 'created_by' => $by->id]);

        Notification::send(
            User::query()->role([StaffRole::Owner->value, StaffRole::Manager->value])->where('is_active', true)->get(),
            new StaffAlert("Fault reported: {$task->locationLabel()}", $task->title.' ('.$priority->label().' priority)', url('/admin/tasks'), urgent: $priority === TaskPriority::Urgent),
        );

        return $task;
    }

    /**
     * Room readiness changes only through here, so every change is logged (HK-06).
     */
    public static function setRoomStatus(Room $room, RoomCleaningStatus $status, ?User $by = null, ?string $reason = null): void
    {
        $previous = $room->cleaning_status;

        if ($previous === $status) {
            return;
        }

        $room->forceFill(['cleaning_status' => $status, 'cleaning_status_changed_at' => now()])->save();

        activity('housekeeping')
            ->performedOn($room)
            ->causedBy($by)
            ->event('room_status_changed')
            ->withProperties(['from' => $previous?->value, 'to' => $status->value, 'reason' => $reason])
            ->log("Room {$room->name}: {$status->label()}");
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(TaskType $type, array $data): array
    {
        $room = isset($data['room_id']) ? Room::query()->find($data['room_id']) : null;
        $checklist = array_values(array_filter(array_map('trim', array_map('strval', $data['checklist'] ?? [])), fn (string $label): bool => $label !== ''));
        $priority = $data['priority'] ?? TaskPriority::Normal;

        return [
            'type' => $type,
            'title' => filled($data['title'] ?? null) ? trim((string) $data['title']) : $type->label().($room ? " — {$room->name}" : ''),
            'room_id' => $room?->id,
            'asset_id' => $data['asset_id'] ?? null,
            'location' => filled($data['location'] ?? null) ? trim((string) $data['location']) : null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'priority' => $priority instanceof TaskPriority ? $priority : TaskPriority::from((string) $priority),
            'status' => TaskStatus::Open,
            'due_at' => $data['due_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'checklist' => $checklist !== []
                ? array_map(fn (string $label): array => ['label' => $label, 'done' => false], $checklist)
                : TaskChecklistTemplate::checklistFor($type),
            'estimated_minutes' => CleaningEstimate::minutesFor($room, $type),
        ];
    }

    private function ensureAssignable(?int $userId, TaskType $type): void
    {
        if ($userId === null) {
            return;
        }

        $assignee = User::query()->find($userId);

        if ($assignee === null || ! $assignee->is_active) {
            throw new InvalidArgumentException('That staff member is not active.');
        }

        $roles = $type->isHousekeeping()
            ? [StaffRole::HousekeepingWorker, StaffRole::Manager, StaffRole::Owner]
            : [StaffRole::MaintenanceWorker, StaffRole::HousekeepingWorker, StaffRole::Manager, StaffRole::Owner];

        if (! $assignee->hasAnyRole(array_map(fn (StaffRole $role): string => $role->value, $roles))) {
            throw new InvalidArgumentException("{$assignee->name} cannot be given {$type->label()} tasks.");
        }
    }

    private function notifyAssignee(Task $task): void
    {
        if ($task->assigned_to === null || ! $task->wasChanged('assigned_to') && ! $task->wasRecentlyCreated) {
            return;
        }

        $task->assignee?->notify(new StaffAlert(
            "New task: {$task->title}",
            "{$task->type->label()} at {$task->locationLabel()}, {$task->priority->label()} priority.",
            url('/staff/my-tasks'),
            urgent: $task->priority === TaskPriority::Urgent,
        ));
    }
}
