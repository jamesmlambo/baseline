<?php

declare(strict_types=1);

use App\Actions\Housekeeping\RunMaintenanceSchedules;
use App\Enums\RepeatUnit;
use App\Enums\StaffRole;
use App\Enums\TaskType;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\MaintenanceSchedule;
use App\Models\Task;
use App\Services\Housekeeping\TaskManager;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2027-06-01 08:00', 'Africa/Johannesburg'));
    $this->manager = staffUser(StaffRole::Manager);
    $this->worker = staffUser(StaffRole::HousekeepingWorker);
});

/**
 * Runs the daily job on a date, as the scheduler does at 06:05.
 */
function runSchedulesOn(string $date): int
{
    test()->travelTo(CarbonImmutable::parse("{$date} 06:05", 'Africa/Johannesburg'));

    return app(RunMaintenanceSchedules::class)->handle();
}

/**
 * @return list<string>
 */
function dueDates(): array
{
    return Task::query()->orderBy('due_at')->get()
        ->map(fn (Task $task): string => $task->due_at->setTimezone('Africa/Johannesburg')->format('Y-m-d H:i'))
        ->all();
}

it('HK-08 repeats a task every week for the same worker at the same time', function (): void {
    $first = app(TaskManager::class)->createRecurring(
        ['type' => TaskType::DeepClean->value, 'title' => 'Wash curtains', 'assigned_to' => $this->worker->id, 'due_at' => '2027-06-01 10:00'],
        RepeatUnit::Week, 1, null, $this->manager,
    );

    $schedule = MaintenanceSchedule::query()->sole();

    expect($first->maintenance_schedule_id)->toBe($schedule->id)
        ->and($schedule->next_due_on->toDateString())->toBe('2027-06-08')
        ->and($schedule->repeatLabel())->toBe('Every week')
        ->and(runSchedulesOn('2027-06-07'))->toBe(0)
        ->and(runSchedulesOn('2027-06-08'))->toBe(1)
        ->and(runSchedulesOn('2027-06-08'))->toBe(0)
        ->and(dueDates())->toBe(['2027-06-01 10:00', '2027-06-08 10:00'])
        ->and(Task::query()->pluck('assigned_to')->unique()->all())->toBe([$this->worker->id]);
});

it('HK-08 repeats every N days and every month, keeping the day of the month', function (): void {
    app(TaskManager::class)->createRecurring(
        ['type' => TaskType::DeepClean->value, 'title' => 'Every 2 days', 'due_at' => '2027-06-01 09:00'],
        RepeatUnit::Day, 2, null, $this->manager,
    );
    app(TaskManager::class)->createRecurring(
        ['type' => TaskType::DeepClean->value, 'title' => 'Month end stock count', 'due_at' => '2027-01-31 16:00'],
        RepeatUnit::Month, 1, null, $this->manager,
    );

    $daily = MaintenanceSchedule::query()->where('title', 'Every 2 days')->sole();
    $monthly = MaintenanceSchedule::query()->where('title', 'Month end stock count')->sole();

    expect($daily->next_due_on->toDateString())->toBe('2027-06-03')
        ->and($monthly->repeatLabel())->toBe('Every month')
        ->and($monthly->nextOccurrenceAfter(CarbonImmutable::parse('2027-01-31'))->toDateString())->toBe('2027-02-28')
        ->and($monthly->nextOccurrenceAfter(CarbonImmutable::parse('2027-02-28'))->toDateString())->toBe('2027-03-31');
});

it('HK-08 stops repeating after the end date and switches the schedule off', function (): void {
    app(TaskManager::class)->createRecurring(
        ['type' => TaskType::DeepClean->value, 'title' => 'Short run', 'due_at' => '2027-06-01 10:00'],
        RepeatUnit::Week, 1, '2027-06-10', $this->manager,
    );

    runSchedulesOn('2027-06-08');
    runSchedulesOn('2027-06-15');

    expect(dueDates())->toBe(['2027-06-01 10:00', '2027-06-08 10:00'])
        ->and(MaintenanceSchedule::query()->sole()->is_active)->toBeFalse();
});

it('HK-08 makes a repeating task from the New task form', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->manager);

    Livewire::test(ListTasks::class)
        ->callAction('newTask', data: [
            'type' => TaskType::DeepClean->value,
            'title' => 'Change pool towels',
            'assigned_to' => $this->worker->id,
            'priority' => 'normal',
            'schedule' => TaskResource::MONTHLY,
            'date' => '2027-06-02',
            'time' => '09:00',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Repeating task created');

    expect(Task::query()->sole()->title)->toBe('Change pool towels')
        ->and(MaintenanceSchedule::query()->sole()->next_due_on->toDateString())->toBe('2027-07-02');
});

it('HK-08 needs a start date before a task can repeat', function (): void {
    expect(fn () => app(TaskManager::class)->createRecurring(['type' => TaskType::DeepClean->value], RepeatUnit::Day, 1, null, $this->manager))
        ->toThrow(InvalidArgumentException::class, 'starts');

    expect(Task::query()->count())->toBe(0);
});

it('HK-08 repeats a task on the ticked days at the chosen time, from the start date', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->manager);

    // Tuesday 1 June 2027; ticked Monday, Wednesday and Friday at 09:00.
    Livewire::test(ListTasks::class)
        ->callAction('newTask', data: [
            'type' => TaskType::DeepClean->value,
            'title' => 'Clean the lounge',
            'assigned_to' => $this->worker->id,
            'priority' => 'normal',
            'schedule' => TaskResource::CHOSEN_DAYS,
            'days' => ['1', '3', '5'],
            'date' => '2027-06-01',
            'time' => '09:00',
        ])
        ->assertHasNoActionErrors();

    $schedule = MaintenanceSchedule::query()->sole();

    expect($schedule->repeatLabel())->toBe('Every Mon, Wed, Fri')
        ->and($schedule->next_due_on->toDateString())->toBe('2027-06-04');

    runSchedulesOn('2027-06-04');
    runSchedulesOn('2027-06-05');
    runSchedulesOn('2027-06-07');

    expect(dueDates())->toBe(['2027-06-02 09:00', '2027-06-04 09:00', '2027-06-07 09:00'])
        ->and($schedule->fresh()->next_due_on->toDateString())->toBe('2027-06-09');
});

it('HK-08 makes a one-day task for the chosen date and time, without repeating', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->manager);

    Livewire::test(ListTasks::class)
        ->callAction('newTask', data: [
            'type' => TaskType::DeepClean->value,
            'assigned_to' => $this->worker->id,
            'priority' => 'normal',
            'schedule' => TaskResource::ONE_DAY,
            'date' => '2027-06-03',
            'time' => '14:30',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Task created');

    expect(dueDates())->toBe(['2027-06-03 14:30'])
        ->and(MaintenanceSchedule::query()->count())->toBe(0);
});

it('HK-08 names weekend and weekday patterns, and refuses ticked days that never fall before the end date', function (): void {
    $weekend = new MaintenanceSchedule(['repeat_unit' => RepeatUnit::Week, 'repeat_every' => 1, 'weekdays' => [6, 7]]);
    $weekdays = new MaintenanceSchedule(['repeat_unit' => RepeatUnit::Week, 'repeat_every' => 1, 'weekdays' => [1, 2, 3, 4, 5]]);

    expect($weekend->repeatLabel())->toBe('Every weekend (Sat & Sun)')
        ->and($weekdays->repeatLabel())->toBe('Every weekday (Mon–Fri)');

    // Starts Tuesday 1 June, ends Thursday 3 June, but only Saturdays are ticked.
    expect(fn () => app(TaskManager::class)->createRecurring(
        ['type' => TaskType::DeepClean->value, 'due_at' => '2027-06-01 09:00'],
        RepeatUnit::Week, 1, '2027-06-03', $this->manager, [6],
    ))->toThrow(InvalidArgumentException::class, 'None of the chosen days');
});

it('HK-08 moves a recurring job to the next ticked day when the days are changed', function (): void {
    $schedule = MaintenanceSchedule::query()->create([
        'title' => 'Sweep the stoep', 'task_type' => TaskType::Gardening, 'repeat_every' => 1, 'repeat_unit' => RepeatUnit::Week,
        'next_due_on' => '2027-06-01',
    ]);

    $schedule->update(['weekdays' => [5]]);

    expect($schedule->fresh()->next_due_on->toDateString())->toBe('2027-06-04');
});
