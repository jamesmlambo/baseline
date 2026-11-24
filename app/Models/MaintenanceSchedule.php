<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RepeatUnit;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A task that repeats (HK-08): on chosen days of the week (e.g. Monday, Wednesday and Friday at
 * 09:00), or every N days, weeks or months. Preventive maintenance such as pool chemicals, or any
 * recurring job given to a worker. A task is created on each due date.
 */
#[Fillable([
    'title', 'task_type', 'asset_id', 'room_id', 'location', 'repeat_every', 'repeat_unit', 'weekdays', 'starts_on', 'next_due_on',
    'ends_on', 'due_time', 'assigned_to', 'priority', 'checklist', 'notes', 'is_active',
])]
class MaintenanceSchedule extends Model
{
    use LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true, 'repeat_unit' => 'day', 'repeat_every' => 1];

    protected static function booted(): void
    {
        // The repetition counts from `starts_on`. When staff set a new next due date that is not on
        // the current pattern, or change how often it repeats, the pattern restarts from that date.
        static::saving(function (self $schedule): void {
            if ($schedule->starts_on === null || ($schedule->exists && $schedule->isDirty(['repeat_every', 'repeat_unit', 'weekdays']))) {
                $schedule->starts_on = $schedule->next_due_on;
            } elseif ($schedule->exists && $schedule->isDirty('next_due_on') && $schedule->next_due_on !== null) {
                $due = CarbonImmutable::parse($schedule->next_due_on->toDateString(), 'Africa/Johannesburg');
                $onPattern = $schedule->withoutEnd()->nextOccurrenceAfter($due->subDay())?->isSameDay($due) ?? false;

                if (! $onPattern) {
                    $schedule->starts_on = $schedule->next_due_on;
                }
            }

            // With ticked days, the next task always falls on one of them.
            if ($schedule->chosenWeekdays() !== [] && $schedule->next_due_on !== null && $schedule->starts_on !== null) {
                $due = CarbonImmutable::parse($schedule->next_due_on->toDateString(), 'Africa/Johannesburg');

                if (! in_array($due->isoWeekday(), $schedule->chosenWeekdays(), true)) {
                    $next = $schedule->withoutEnd()->nextOccurrenceAfter($due->subDay());
                    $schedule->next_due_on = $next?->toDateString() ?? $schedule->next_due_on;
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'task_type' => TaskType::class,
            'repeat_unit' => RepeatUnit::class,
            'repeat_every' => 'integer',
            'weekdays' => 'array',
            'starts_on' => 'date',
            'next_due_on' => 'date',
            'ends_on' => 'date',
            'priority' => TaskPriority::class,
            'checklist' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The ticked days of the week (ISO 1 = Monday … 7 = Sunday), sorted. Empty when the job does not repeat on chosen days.
     *
     * @return list<int>
     */
    public function chosenWeekdays(): array
    {
        if ($this->repeat_unit !== RepeatUnit::Week) {
            return [];
        }

        $days = array_values(array_unique(array_filter(array_map('intval', $this->weekdays ?? []), fn (int $day): bool => $day >= 1 && $day <= 7)));
        sort($days);

        return $days;
    }

    public function repeatLabel(): string
    {
        $days = $this->chosenWeekdays();

        if ($days === []) {
            return $this->repeat_unit->label($this->repeat_every);
        }

        $names = match ($days) {
            [1, 2, 3, 4, 5, 6, 7] => 'day',
            [1, 2, 3, 4, 5] => 'weekday (Mon–Fri)',
            [6, 7] => 'weekend (Sat & Sun)',
            default => implode(', ', array_map(fn (int $day): string => substr(RosterPattern::WEEKDAYS[$day], 0, 3), $days)),
        };

        return 'Every '.$names.($this->repeat_every > 1 ? ", every {$this->repeat_every} weeks" : '');
    }

    /**
     * The first due date after `$after`, counted from the start date so monthly tasks keep their day
     * and fortnightly tasks keep their weeks. Null when the schedule has ended by then.
     */
    public function nextOccurrenceAfter(CarbonImmutable $after): ?CarbonImmutable
    {
        $first = CarbonImmutable::parse($this->starts_on->toDateString(), 'Africa/Johannesburg');
        $days = $this->chosenWeekdays();

        if ($days !== []) {
            return $this->nextChosenDayAfter($first, $after, $days);
        }

        $step = max(1, $this->repeat_every);
        $count = 0;
        $next = $first;

        while ($next->lessThanOrEqualTo($after)) {
            $count += $step;
            $next = $this->repeat_unit->occurrence($first, $count);
        }

        return $this->ends_on !== null && $next->toDateString() > $this->ends_on->toDateString() ? null : $next;
    }

    /**
     * Next ticked day after `$after`, not before the start date, in a week that is on the
     * "every N weeks" cycle counted from the start week.
     *
     * @param  list<int>  $days
     */
    private function nextChosenDayAfter(CarbonImmutable $first, CarbonImmutable $after, array $days): ?CarbonImmutable
    {
        $every = max(1, $this->repeat_every);
        $startWeek = $first->startOfWeek(CarbonImmutable::MONDAY);
        $candidate = $after->startOfDay()->addDay()->max($first);

        // Every N weeks: at most N weeks to look through before a ticked day comes round again.
        for ($i = 0; $i < 7 * $every + 7; $i++, $candidate = $candidate->addDay()) {
            $weekNumber = intdiv((int) $startWeek->diffInDays($candidate->startOfWeek(CarbonImmutable::MONDAY)), 7);

            if (in_array($candidate->isoWeekday(), $days, true) && $weekNumber % $every === 0) {
                return $this->ends_on !== null && $candidate->toDateString() > $this->ends_on->toDateString() ? null : $candidate;
            }
        }

        return null;
    }

    private function withoutEnd(): self
    {
        $copy = clone $this;
        $copy->ends_on = null;

        return $copy;
    }

    /**
     * When a task for this date is due: the schedule's time of day, or the end of the day.
     */
    public function dueAtOn(CarbonImmutable $date): CarbonImmutable
    {
        if ($this->due_time === null) {
            return $date->endOfDay();
        }

        [$hour, $minute] = array_map('intval', explode(':', (string) $this->due_time));

        return $date->setTime($hour, $minute);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('maintenance')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
