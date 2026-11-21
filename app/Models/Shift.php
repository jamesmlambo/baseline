<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A shift with its valid clock-in/out windows (ATT-03). A shift that ends at or before its start
 * time runs past midnight; its work date is the day it starts.
 */
#[Fillable(['name', 'department_id', 'starts_at', 'ends_at', 'clock_in_opens_minutes', 'late_after_minutes', 'clock_out_closes_minutes', 'is_active', 'is_demo'])]
class Shift extends Model
{
    use LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['clock_in_opens_minutes' => 60, 'late_after_minutes' => 0, 'clock_out_closes_minutes' => 180, 'is_active' => true, 'is_demo' => false];

    protected function casts(): array
    {
        return [
            'clock_in_opens_minutes' => 'integer',
            'late_after_minutes' => 'integer',
            'clock_out_closes_minutes' => 'integer',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('people')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function periodOn(CarbonImmutable $workDate): array
    {
        $day = $workDate->setTimezone('Africa/Johannesburg')->startOfDay();
        $start = CarbonImmutable::parse($day->toDateString().' '.$this->starts_at, 'Africa/Johannesburg');
        $end = CarbonImmutable::parse($day->toDateString().' '.$this->ends_at, 'Africa/Johannesburg');

        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * The window in which punches count for this shift.
     *
     * @return array{opens: CarbonImmutable, closes: CarbonImmutable}
     */
    public function windowOn(CarbonImmutable $workDate): array
    {
        $period = $this->periodOn($workDate);

        return [
            'opens' => $period['start']->subMinutes($this->clock_in_opens_minutes),
            'closes' => $period['end']->addMinutes($this->clock_out_closes_minutes),
        ];
    }

    public function timesLabel(): string
    {
        return substr((string) $this->starts_at, 0, 5).'–'.substr((string) $this->ends_at, 0, 5);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
