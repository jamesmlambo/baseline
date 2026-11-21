<?php

declare(strict_types=1);

require_once __DIR__.'/../Finance/FinanceTestHelpers.php';

use App\Actions\Reception\CheckIn;
use App\Actions\Reception\CheckOut;
use App\Enums\PaymentMethod;
use App\Enums\StaffRole;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Task;
use App\Models\User;
use App\Services\Housekeeping\TaskManager;
use App\Services\Payments\PaymentLedger;

/**
 * A paid stay that has been checked in and checked out again (queue runs synchronously in tests).
 */
function checkedOutStay(): Booking
{
    $booking = reserve();
    $receptionist = staffUser(StaffRole::Receptionist);
    app(PaymentLedger::class)->recordAtReception($booking, PaymentMethod::Cash, $booking->deposit_due_cents, $receptionist);
    app(CheckIn::class)->handle($booking->fresh(), $receptionist, ['full_name' => 'Lerato Demo']);
    app(CheckOut::class)->handle($booking->fresh(), $receptionist);

    return $booking->fresh();
}

function stayRoom(Booking $booking): Room
{
    return $booking->activeBookingRooms()->with('room')->sole()->room->fresh();
}

/**
 * A task of this type given to a worker, created by a manager.
 *
 * @param  list<string>  $checklist
 */
function taskFor(User $worker, TaskType $type = TaskType::CheckoutClean, ?Room $room = null, array $checklist = ['Beds', 'Floors']): Task
{
    return app(TaskManager::class)->create([
        'type' => $type,
        'room_id' => ($room ?? Room::factory()->create())->id,
        'assigned_to' => $worker->id,
        'checklist' => $checklist,
    ], staffUser(StaffRole::Manager));
}

/**
 * The worker finishes the task with every checklist item ticked.
 */
function finishTask(Task $task, User $worker): Task
{
    return app(TaskManager::class)->complete($task->fresh(), $worker, array_column($task->checklistItems(), 'label'), 'All done');
}

function openTasks(): int
{
    return Task::query()->whereIn('status', [TaskStatus::Open, TaskStatus::InProgress])->count();
}
