<?php

declare(strict_types=1);

namespace App\Services\Staff\Attendance;

use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Enums\StaffRole;
use App\Models\AttendanceImport;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stores raw punches (ATT-02): idempotent imports keyed on source + device + person + time +
 * direction, and authorised manual entries with a reason (ATT-01/04). Affected days are
 * recalculated afterwards.
 */
class PunchRecorder
{
    public function __construct(private readonly AttendanceCalculator $calculator) {}

    public function import(AttendanceSource $source, User $by, ?string $filename = null, ?CarbonImmutable $from = null, ?CarbonImmutable $until = null): AttendanceImport
    {
        $this->authorize($by);

        $batch = AttendanceImport::query()->create(['source' => $source->source(), 'filename' => $filename, 'user_id' => $by->id]);
        $employees = Employee::query()->whereNotNull('device_person_id')->pluck('id', 'device_person_id');
        $counts = ['rows_read' => 0, 'rows_added' => 0, 'rows_duplicate' => 0, 'rows_failed' => 0];
        $errors = [];
        $touched = [];

        foreach ($source->read($from ?? CarbonImmutable::create(2000), $until ?? CarbonImmutable::now()->addDay()) as $item) {
            $counts['rows_read']++;

            if ($item instanceof SourceError) {
                $counts['rows_failed']++;
                $errors[] = ['row' => $item->row, 'message' => $item->message];

                continue;
            }

            $employeeId = $employees[$item->devicePersonId] ?? null;
            $inserted = DB::table('attendance_punches')->insertOrIgnore([
                'source' => $item->source->value,
                'device_serial' => $item->deviceSerial,
                'device_person_id' => $item->devicePersonId,
                'employee_id' => $employeeId,
                'punched_at' => $item->punchedAt->toIso8601String(),
                'direction' => $item->direction->value,
                'verify_mode' => $item->verifyMode,
                'dedup_key' => AttendancePunch::dedupKey($item->source, $item->deviceSerial, $item->devicePersonId, $item->punchedAt, $item->direction),
                'attendance_import_id' => $batch->id,
                'raw' => json_encode($item->raw),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                $counts['rows_duplicate']++;

                continue;
            }

            $counts['rows_added']++;

            if ($employeeId !== null) {
                $touched[$employeeId][$item->punchedAt->setTimezone('Africa/Johannesburg')->toDateString()] = true;
            }
        }

        $batch->forceFill($counts + ['errors' => array_slice($errors, 0, 200)])->save();

        $this->recalculate($touched);

        activity('attendance')->performedOn($batch)->causedBy($by)->event('punches_imported')
            ->withProperties($counts)->log("Imported attendance from {$source->source()->label()}");

        return $batch;
    }

    /**
     * ATT-01/04: a punch entered by hand (e.g. the terminal was down), with a reason.
     */
    public function manual(Employee $employee, CarbonImmutable $punchedAt, PunchDirection $direction, string $reason, User $by): AttendancePunch
    {
        $this->authorize($by);

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Give a reason for the manual entry.');
        }

        if ($punchedAt->isFuture()) {
            throw new InvalidArgumentException('A punch cannot be in the future.');
        }

        $personId = 'employee:'.$employee->id;
        $key = AttendancePunch::dedupKey(PunchSource::Manual, null, $personId, $punchedAt, $direction);

        if (AttendancePunch::query()->where('dedup_key', $key)->exists()) {
            throw new InvalidArgumentException('That punch has already been recorded.');
        }

        $punch = AttendancePunch::query()->create([
            'source' => PunchSource::Manual,
            'device_person_id' => $personId,
            'employee_id' => $employee->id,
            'punched_at' => $punchedAt,
            'direction' => $direction,
            'dedup_key' => $key,
            'entered_by' => $by->id,
            'reason' => trim($reason),
        ]);

        activity('attendance')->performedOn($punch)->causedBy($by)->event('manual_punch')
            ->withProperties(['employee_id' => $employee->id, 'punched_at' => $punchedAt->toIso8601String(), 'direction' => $direction->value, 'reason' => trim($reason)])
            ->log("Manual {$direction->label()} punch for {$employee->full_name}");

        $this->recalculate([$employee->id => [$punchedAt->setTimezone('Africa/Johannesburg')->toDateString() => true]]);

        return $punch;
    }

    /**
     * After a device person ID is set on an employee, attach their earlier unmatched punches.
     */
    public function linkUnmatched(Employee $employee): int
    {
        if (blank($employee->device_person_id)) {
            return 0;
        }

        $dates = [];
        $linked = 0;

        AttendancePunch::query()->whereNull('employee_id')->where('device_person_id', $employee->device_person_id)->each(function (AttendancePunch $punch) use ($employee, &$dates, &$linked): void {
            $punch->employee_id = $employee->id;
            $punch->save();
            $dates[$punch->punched_at->setTimezone('Africa/Johannesburg')->toDateString()] = true;
            $linked++;
        });

        $this->recalculate([$employee->id => $dates]);

        return $linked;
    }

    /**
     * @param  array<int, array<string, bool>>  $touched  employee id => [date => true]
     */
    private function recalculate(array $touched): void
    {
        foreach ($touched as $employeeId => $dates) {
            $employee = Employee::query()->find($employeeId);

            if ($employee === null) {
                continue;
            }

            foreach (array_keys($dates) as $date) {
                $day = CarbonImmutable::parse($date, 'Africa/Johannesburg');
                // A punch after midnight may belong to yesterday's night shift.
                $this->calculator->derive($employee, $day->subDay());
                $this->calculator->derive($employee, $day);
            }
        }
    }

    private function authorize(User $by): void
    {
        if (! $by->is_active || ! $by->hasAnyRole([StaffRole::Owner->value, StaffRole::Manager->value])) {
            throw new AuthorizationException('Only the owner or a manager can import or enter attendance.');
        }
    }
}
