<?php

declare(strict_types=1);

require_once __DIR__.'/HousekeepingTestHelpers.php';

use App\Actions\Housekeeping\GenerateDeepCleans;
use App\Actions\Housekeeping\RunMaintenanceSchedules;
use App\Enums\InspectionStatus;
use App\Enums\RepeatUnit;
use App\Enums\RoomCleaningStatus;
use App\Enums\StaffRole;
use App\Enums\StockMovementReason;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Jobs\CreateTurnoverTasks;
use App\Models\Inspection;
use App\Models\LostPropertyItem;
use App\Models\MaintenanceSchedule;
use App\Models\Room;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Task;
use App\Notifications\StaffAlert;
use App\Services\Housekeeping\Inspections;
use App\Services\Housekeeping\StockLedger;
use App\Services\Housekeeping\TaskManager;
use Carbon\CarbonImmutable;
use Database\Seeders\TaskChecklistSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    seedFinanceSettings();
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2027-06-10 14:00', 'Africa/Johannesburg'));
});

// --- Checkout turnover (CHK-03, CHK-04) -------------------------------------------------

it('CHK-03 makes the room dirty and creates one checkout clean at checkout', function (): void {
    $booking = checkedOutStay();
    $room = stayRoom($booking);

    $task = Task::query()->sole();
    expect($room->cleaning_status)->toBe(RoomCleaningStatus::Dirty)
        ->and($task->type)->toBe(TaskType::CheckoutClean)
        ->and($task->room_id)->toBe($room->id)
        ->and($task->booking_id)->toBe($booking->id)
        ->and($task->source)->toBe(Task::SOURCE_CHECKOUT);
});

it('CHK-04 does not duplicate the checkout clean or reset the room when the job runs again', function (): void {
    $booking = checkedOutStay();
    $room = stayRoom($booking);
    $worker = staffUser(StaffRole::HousekeepingWorker);
    $task = Task::query()->sole();
    app(TaskManager::class)->assign($task, $worker, staffUser(StaffRole::Receptionist));
    app(TaskManager::class)->start($task->fresh(), $worker);

    (new CreateTurnoverTasks($booking->id))->handle(app(TaskManager::class));

    expect(Task::query()->count())->toBe(1)
        ->and($room->fresh()->cleaning_status)->toBe(RoomCleaningStatus::Cleaning);
});

// --- Creating and assigning (HK-01..03) -------------------------------------------------

it('HK-03 lets reception create housekeeping tasks but not maintenance', function (): void {
    $receptionist = staffUser(StaffRole::Receptionist);

    $clean = app(TaskManager::class)->create(['type' => TaskType::DailyClean, 'room_id' => Room::factory()->create()->id], $receptionist);

    expect($clean->type)->toBe(TaskType::DailyClean)
        ->and(fn () => app(TaskManager::class)->create(['type' => TaskType::Gardening, 'location' => 'Front garden'], $receptionist))
        ->toThrow(AuthorizationException::class);
});

it('HK-03 lets a manager create gardening and maintenance tasks', function (): void {
    $task = app(TaskManager::class)->create(['type' => TaskType::Pool, 'location' => 'Pool', 'assigned_to' => staffUser(StaffRole::MaintenanceWorker)->id], staffUser(StaffRole::Manager));

    expect($task->type)->toBe(TaskType::Pool)->and($task->assigned_to)->not->toBeNull();
});

it('HK-03 only gives housekeeping tasks to housekeeping staff', function (): void {
    expect(fn () => app(TaskManager::class)->create([
        'type' => TaskType::CheckoutClean,
        'room_id' => Room::factory()->create()->id,
        'assigned_to' => staffUser(StaffRole::ContentEditor)->id,
    ], staffUser(StaffRole::Receptionist)))->toThrow(InvalidArgumentException::class);
});

it('HK-02 tells the worker when a task is given to them', function (): void {
    Notification::fake();
    $worker = staffUser(StaffRole::HousekeepingWorker);

    taskFor($worker);

    Notification::assertSentTo($worker, StaffAlert::class);
});

it('HK-02 copies the standard checklist onto new tasks of that type', function (): void {
    $this->seed(TaskChecklistSeeder::class);

    $task = app(TaskManager::class)->create(['type' => TaskType::DeepClean, 'room_id' => Room::factory()->create()->id], staffUser(StaffRole::Receptionist));

    expect(array_column($task->checklistItems(), 'label'))->toContain('Under beds', 'Aluminium windows and doors', 'Coffee and sugar');
});

// --- Completing (HK-02) -----------------------------------------------------------------

it('HK-02 needs every checklist item ticked before a task is done', function (): void {
    $worker = staffUser(StaffRole::HousekeepingWorker);
    $task = taskFor($worker, checklist: ['Beds', 'Floors']);

    expect(fn () => app(TaskManager::class)->complete($task, $worker, ['Beds']))->toThrow(InvalidArgumentException::class, 'Floors')
        ->and($task->fresh()->status)->toBe(TaskStatus::Open);
});

it('HK-02 needs a note when a task has no checklist', function (): void {
    $worker = staffUser(StaffRole::MaintenanceWorker);
    $task = taskFor($worker, TaskType::Repairs, checklist: []);

    expect(fn () => app(TaskManager::class)->complete($task, $worker, []))->toThrow(InvalidArgumentException::class);

    app(TaskManager::class)->complete($task->fresh(), $worker, [], 'Replaced the tap washer');

    expect($task->fresh()->status)->toBe(TaskStatus::Done)->and($task->fresh()->completed_by)->toBe($worker->id);
});

it('HK-02 lets only the assigned worker start or finish a task', function (): void {
    $task = taskFor(staffUser(StaffRole::HousekeepingWorker));
    $someoneElse = staffUser(StaffRole::HousekeepingWorker);

    expect(fn () => app(TaskManager::class)->start($task, $someoneElse))->toThrow(AuthorizationException::class)
        ->and(fn () => finishTask($task, $someoneElse))->toThrow(AuthorizationException::class);
});

// --- Inspections, rework, readiness (HK-05, HK-06) --------------------------------------

it('HK-06 sends a finished checkout clean to inspection, and a pass makes the room ready', function (): void {
    $worker = staffUser(StaffRole::HousekeepingWorker);
    $room = Room::factory()->create();
    $task = taskFor($worker, room: $room);

    finishTask($task, $worker);

    $inspection = Inspection::query()->sole();
    expect($room->fresh()->cleaning_status)->toBe(RoomCleaningStatus::AwaitingInspection)
        ->and($inspection->status)->toBe(InspectionStatus::Pending);

    app(Inspections::class)->record($inspection, staffUser(StaffRole::Inspector), ['Beds' => ['score' => 5], 'Floors' => ['score' => 4]]);

    expect($room->fresh()->cleaning_status)->toBe(RoomCleaningStatus::Ready)
        ->and($inspection->fresh()->status)->toBe(InspectionStatus::Passed);
});

it('HK-05 sends failed items back to the same worker as rework and inspects again', function (): void {
    Notification::fake();
    $worker = staffUser(StaffRole::HousekeepingWorker);
    $inspector = staffUser(StaffRole::Inspector);
    $room = Room::factory()->create();
    finishTask(taskFor($worker, room: $room), $worker);

    app(Inspections::class)->record(Inspection::query()->sole(), $inspector, [
        'Beds' => ['score' => 5],
        'Floors' => ['score' => 2, 'comment' => 'Sand under the bed'],
    ]);

    $rework = Task::query()->where('source', Task::SOURCE_REWORK)->sole();
    expect($room->fresh()->cleaning_status)->toBe(RoomCleaningStatus::Dirty)
        ->and($rework->assigned_to)->toBe($worker->id)
        ->and(array_column($rework->checklistItems(), 'label'))->toBe(['Floors'])
        ->and($rework->priority)->toBe(TaskPriority::High);
    Notification::assertSentTo($worker, StaffAlert::class, fn (StaffAlert $alert): bool => str_starts_with($alert->subject, 'Rework needed'));

    finishTask($rework, $worker);
    $reinspection = Inspection::query()->where('task_id', $rework->id)->sole();
    app(Inspections::class)->record($reinspection, $inspector, ['Floors' => ['score' => 5]]);

    expect($room->fresh()->cleaning_status)->toBe(RoomCleaningStatus::Ready);
});

it('HK-05 does not let anyone inspect their own clean', function (): void {
    $manager = staffUser(StaffRole::Manager, StaffRole::HousekeepingWorker);
    $task = taskFor($manager);
    finishTask($task, $manager);

    expect(fn () => app(Inspections::class)->record(Inspection::query()->sole(), $manager, ['Beds' => ['score' => 5], 'Floors' => ['score' => 5]]))
        ->toThrow(AuthorizationException::class);
});

it('HK-05 does not let housekeeping workers score inspections', function (): void {
    $worker = staffUser(StaffRole::HousekeepingWorker);
    finishTask(taskFor($worker), $worker);

    expect(fn () => app(Inspections::class)->record(Inspection::query()->sole(), staffUser(StaffRole::HousekeepingWorker), ['Beds' => ['score' => 5], 'Floors' => ['score' => 5]]))
        ->toThrow(AuthorizationException::class);
});

it('HK-05 needs a score for every item, within the scale', function (): void {
    $worker = staffUser(StaffRole::HousekeepingWorker);
    finishTask(taskFor($worker), $worker);
    $inspection = Inspection::query()->sole();
    $inspector = staffUser(StaffRole::Inspector);

    expect(fn () => app(Inspections::class)->record($inspection, $inspector, ['Beds' => ['score' => 5]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(Inspections::class)->record($inspection, $inspector, ['Beds' => ['score' => 9], 'Floors' => ['score' => 5]]))->toThrow(InvalidArgumentException::class)
        ->and($inspection->fresh()->status)->toBe(InspectionStatus::Pending);
});

it('HK-06 lets only the owner mark a room ready without inspection, with a reason that is logged', function (): void {
    $room = Room::factory()->create();
    TaskManager::setRoomStatus($room, RoomCleaningStatus::Dirty);

    expect(fn () => app(Inspections::class)->overrideReady($room, staffUser(StaffRole::Manager), 'Urgent'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(Inspections::class)->overrideReady($room, staffUser(StaffRole::Owner), ' '))->toThrow(InvalidArgumentException::class);

    app(Inspections::class)->overrideReady($room, staffUser(StaffRole::Owner), 'Inspector off sick; checked it myself');

    expect($room->fresh()->cleaning_status)->toBe(RoomCleaningStatus::Ready)
        ->and(Activity::query()->where('event', 'readiness_override')->sole()->properties['reason'])->toBe('Inspector off sick; checked it myself');
});

// --- Monday deep clean (HK-04) ----------------------------------------------------------

it('HK-04 creates one deep clean per active room on Mondays, with the spec checklist', function (): void {
    $this->seed(TaskChecklistSeeder::class);
    Room::factory()->count(2)->create();
    Room::factory()->create(['is_active' => false]);
    $monday = CarbonImmutable::parse('2027-06-14', 'Africa/Johannesburg');

    expect(app(GenerateDeepCleans::class)->handle($monday))->toBe(2)
        ->and(app(GenerateDeepCleans::class)->handle($monday))->toBe(0)
        ->and(app(GenerateDeepCleans::class)->handle($monday->addDay()))->toBe(0)
        ->and(Task::query()->where('type', TaskType::DeepClean)->count())->toBe(2)
        ->and(count(Task::query()->first()->checklistItems()))->toBe(12);
});

// --- Maintenance (HK-08) ----------------------------------------------------------------

it('HK-08 creates preventive work when it is due and moves the schedule on', function (): void {
    $worker = staffUser(StaffRole::MaintenanceWorker);
    $schedule = MaintenanceSchedule::query()->create([
        'title' => 'Backwash pool filter', 'task_type' => TaskType::Pool, 'location' => 'Pool',
        'repeat_every' => 7, 'repeat_unit' => RepeatUnit::Day, 'next_due_on' => '2027-06-01', 'assigned_to' => $worker->id,
    ]);

    expect(app(RunMaintenanceSchedules::class)->handle())->toBe(1)
        ->and(app(RunMaintenanceSchedules::class)->handle())->toBe(0);

    $task = Task::query()->sole();
    expect($task->assigned_to)->toBe($worker->id)
        ->and($task->maintenance_schedule_id)->toBe($schedule->id)
        ->and($schedule->fresh()->next_due_on->toDateString())->toBe('2027-06-15');
});

it('HK-08 lets any operations worker report a fault and tells managers', function (): void {
    Notification::fake();
    $manager = staffUser(StaffRole::Manager);
    $room = Room::factory()->create();

    $task = app(TaskManager::class)->reportFault(staffUser(StaffRole::HousekeepingWorker), 'Shower drips', $room, priority: TaskPriority::High);

    expect($task->type)->toBe(TaskType::Repairs)->and($task->source)->toBe(Task::SOURCE_FAULT)->and($task->room_id)->toBe($room->id);
    Notification::assertSentTo($manager, StaffAlert::class);
});

it('HK-08 does not let content editors report faults', function (): void {
    expect(fn () => app(TaskManager::class)->reportFault(staffUser(StaffRole::ContentEditor), 'Broken', location: 'Lobby'))
        ->toThrow(AuthorizationException::class);
});

// --- Stock (HK-09) ----------------------------------------------------------------------

it('HK-09 keeps stock as a ledger and never lets it go below zero', function (): void {
    $item = StockItem::query()->create(['name' => 'Toilet paper', 'unit' => 'roll']);
    $worker = staffUser(StaffRole::HousekeepingWorker);

    app(StockLedger::class)->record($item, StockMovementReason::Received, 24, $worker);
    app(StockLedger::class)->record($item, StockMovementReason::Used, 4, $worker);

    expect($item->fresh()->onHand())->toBe(20)
        ->and(fn () => app(StockLedger::class)->record($item, StockMovementReason::Used, 21, $worker))->toThrow(InvalidArgumentException::class)
        ->and(fn () => StockMovement::query()->first()->update(['quantity' => 100]))->toThrow(LogicException::class);
});

it('HK-09 lets only managers correct stock counts, with a reason', function (): void {
    $item = StockItem::query()->create(['name' => 'Sugar sachets', 'unit' => 'each']);

    expect(fn () => app(StockLedger::class)->record($item, StockMovementReason::Adjusted, 5, staffUser(StaffRole::HousekeepingWorker), 'Count'))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(StockLedger::class)->record($item, StockMovementReason::Adjusted, 5, staffUser(StaffRole::Manager)))
        ->toThrow(InvalidArgumentException::class);

    app(StockLedger::class)->record($item, StockMovementReason::Adjusted, 5, staffUser(StaffRole::Manager), 'Stock-take');

    expect($item->fresh()->onHand())->toBe(5);
});

it('HK-09 warns managers once when stock reaches the reorder level, and again after a restock', function (): void {
    Notification::fake();
    $manager = staffUser(StaffRole::Manager);
    $worker = staffUser(StaffRole::HousekeepingWorker);
    $item = StockItem::query()->create(['name' => 'Coffee', 'unit' => 'jar', 'reorder_level' => 2]);
    $ledger = app(StockLedger::class);

    $ledger->record($item, StockMovementReason::Received, 5, $worker);
    $ledger->record($item, StockMovementReason::Used, 3, $worker);
    $ledger->record($item, StockMovementReason::Used, 1, $worker);
    Notification::assertSentToTimes($manager, StaffAlert::class, 1);

    $ledger->record($item, StockMovementReason::Received, 10, $worker);
    $ledger->record($item, StockMovementReason::Used, 9, $worker);
    Notification::assertSentToTimes($manager, StaffAlert::class, 2);
});

// --- Lost property (HK-10) and access ---------------------------------------------------

it('HK-10 keeps lost property away from housekeeping and records who closed an entry', function (): void {
    $receptionist = staffUser(StaffRole::Receptionist);
    $this->actingAs($receptionist);
    $item = LostPropertyItem::query()->create(['found_on' => '2027-06-10', 'found_at' => 'Room 1', 'description' => 'Black phone charger']);

    $item->update(['status' => 'returned', 'outcome_notes' => 'Collected by guest']);

    expect($item->fresh()->resolved_by)->toBe($receptionist->id)
        ->and(staffUser(StaffRole::HousekeepingWorker)->can('viewAny', LostPropertyItem::class))->toBeFalse()
        ->and($receptionist->can('viewAny', LostPropertyItem::class))->toBeTrue();
});

it('SEC-02 keeps housekeeping workers out of the admin task screens', function (): void {
    $this->actingAs(staffUser(StaffRole::HousekeepingWorker))
        ->get('/admin/tasks')
        ->assertForbidden();
});
