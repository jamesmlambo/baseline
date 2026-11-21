<?php

declare(strict_types=1);

require_once __DIR__.'/HousekeepingTestHelpers.php';

use App\Enums\InspectionStatus;
use App\Enums\RoomCleaningStatus;
use App\Enums\StaffRole;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Filament\Pages\RoomStatusBoard;
use App\Filament\Resources\StockItems\Pages\ManageStockItems;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Staff\Resources\Inspections\Pages\ListInspectionsToDo;
use App\Filament\Staff\Resources\MyTasks\Pages\ListMyTasks;
use App\Models\Inspection;
use App\Models\Room;
use App\Models\StockItem;
use App\Models\Task;
use App\Services\Housekeeping\TaskManager;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    seedFinanceSettings();
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2027-06-10 14:00', 'Africa/Johannesburg'));
});

function useStaffPanel(): void
{
    Filament::setCurrentPanel(Filament::getPanel('staff'));
}

function useAdminPanel(): void
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));
}

it('HK-03 lets reception create and assign a clean from the task list', function (): void {
    useAdminPanel();
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $worker = staffUser(StaffRole::HousekeepingWorker);
    $room = Room::factory()->create();

    Livewire::test(ListTasks::class)
        ->callAction('newTask', ['type' => TaskType::DailyClean->value, 'room_id' => $room->id, 'priority' => 'normal'])
        ->assertNotified('Task created');

    $task = Task::query()->sole();
    Livewire::test(ListTasks::class)
        ->callTableAction('assign', $task, ['assigned_to' => $worker->id])
        ->assertNotified('Task assigned');

    expect($task->fresh()->assigned_to)->toBe($worker->id);
});

it('HK-02 shows workers only their own tasks and lets them finish with the checklist', function (): void {
    useStaffPanel();
    $worker = staffUser(StaffRole::HousekeepingWorker);
    $mine = taskFor($worker, checklist: ['Beds', 'Floors']);
    $theirs = taskFor(staffUser(StaffRole::HousekeepingWorker));
    $this->actingAs($worker);

    Livewire::test(ListMyTasks::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs])
        ->callTableAction('start', $mine)
        ->callTableAction('complete', $mine, ['ticked' => ['Beds', 'Floors']])
        ->assertNotified('Done — the room is waiting for inspection');

    expect($mine->fresh()->status)->toBe(TaskStatus::Done);
});

it('HK-02 refuses to finish a task with checklist items left open', function (): void {
    useStaffPanel();
    $worker = staffUser(StaffRole::HousekeepingWorker);
    $task = taskFor($worker, checklist: ['Beds', 'Floors']);
    $this->actingAs($worker);

    Livewire::test(ListMyTasks::class)
        ->callTableAction('complete', $task, ['ticked' => ['Beds']])
        ->assertNotified('Not done');

    expect($task->fresh()->status)->toBe(TaskStatus::Open);
});

it('HK-05 lets an inspector score a room from the staff panel', function (): void {
    useStaffPanel();
    $worker = staffUser(StaffRole::HousekeepingWorker);
    $room = Room::factory()->create();
    finishTask(taskFor($worker, room: $room, checklist: ['Beds']), $worker);
    $inspection = Inspection::query()->sole();
    $this->actingAs(staffUser(StaffRole::Inspector));

    Livewire::test(ListInspectionsToDo::class)
        ->assertCanSeeTableRecords([$inspection])
        ->callTableAction('inspect', $inspection, ['scores' => [['score' => '5', 'comment' => null]]])
        ->assertNotified('Inspection recorded');

    expect($inspection->fresh()->status)->toBe(InspectionStatus::Passed)
        ->and($room->fresh()->cleaning_status)->toBe(RoomCleaningStatus::Ready);
});

it('HK-02 lets a worker report a fault from their task list', function (): void {
    useStaffPanel();
    $this->actingAs(staffUser(StaffRole::HousekeepingWorker));
    $room = Room::factory()->create();

    Livewire::test(ListMyTasks::class)
        ->callAction('reportFault', ['description' => 'Geyser leaking', 'room_id' => $room->id, 'priority' => 'urgent'])
        ->assertNotified('Fault reported — managers have been told');

    expect(Task::query()->where('source', Task::SOURCE_FAULT)->sole()->room_id)->toBe($room->id);
});

it('HK-06 shows room readiness and lets the owner override it', function (): void {
    useAdminPanel();
    $room = Room::factory()->create();
    TaskManager::setRoomStatus($room, RoomCleaningStatus::Dirty);
    $this->actingAs(staffUser(StaffRole::Owner));

    Livewire::test(RoomStatusBoard::class)
        ->assertCanSeeTableRecords([$room])
        ->callTableAction('markReady', $room, ['reason' => 'Checked personally'])
        ->assertNotified('Room marked ready');

    expect($room->fresh()->cleaning_status)->toBe(RoomCleaningStatus::Ready);
});

it('HK-06 hides the readiness override from reception', function (): void {
    useAdminPanel();
    $room = Room::factory()->create();
    TaskManager::setRoomStatus($room, RoomCleaningStatus::Dirty);
    $this->actingAs(staffUser(StaffRole::Receptionist));

    Livewire::test(RoomStatusBoard::class)->assertTableActionHidden('markReady', $room);
});

it('HK-09 records stock received from the stock screen', function (): void {
    useAdminPanel();
    $this->actingAs(staffUser(StaffRole::Manager));
    $item = StockItem::query()->create(['name' => 'Bath towels', 'unit' => 'each', 'reorder_level' => 10]);

    Livewire::test(ManageStockItems::class)
        ->callTableAction('stockReceived', $item, ['quantity' => 30, 'note' => 'Delivery'])
        ->assertNotified('Stock updated');

    expect($item->fresh()->onHand())->toBe(30);
});
