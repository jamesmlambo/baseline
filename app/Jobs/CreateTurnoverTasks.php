<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Enums\RoomCleaningStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Task;
use App\Services\Housekeeping\TaskManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * CHK-03/04: after checkout, every room of the stay becomes Dirty and gets one checkout clean.
 * Safe to run again: the task's idempotency key stops duplicates, and a room that is already
 * further along (cleaning, awaiting inspection, ready again) is not set back to Dirty.
 */
class CreateTurnoverTasks implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $bookingId) {}

    public function handle(TaskManager $tasks): void
    {
        $booking = Booking::query()->find($this->bookingId);

        if ($booking === null || $booking->status !== BookingStatus::CheckedOut) {
            return;
        }

        $booking->activeBookingRooms()->with('room')->get()->each(function (BookingRoom $bookingRoom) use ($booking, $tasks): void {
            $room = $bookingRoom->room;

            if ($room === null) {
                return;
            }

            $arrivalToday = BookingRoom::query()
                ->where('room_id', $room->id)
                ->where('booking_id', '<>', $booking->id)
                ->whereHas('booking', fn ($query) => $query->whereDate('arrival_date', today())->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Hold]))
                ->exists();

            $task = $tasks->createOnce("checkout_clean:{$bookingRoom->id}", TaskType::CheckoutClean, Task::SOURCE_CHECKOUT, [
                'room_id' => $room->id,
                'booking_id' => $booking->id,
                'priority' => $arrivalToday ? TaskPriority::High : TaskPriority::Normal,
                'notes' => $arrivalToday ? 'Another guest arrives in this room today.' : null,
            ]);

            if ($task->wasRecentlyCreated) {
                TaskManager::setRoomStatus($room, RoomCleaningStatus::Dirty, null, "Checkout of {$booking->reference}");
            }
        });
    }
}
