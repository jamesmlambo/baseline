<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EmployeeStatus;
use App\Enums\StaffRole;
use App\Models\Employee;
use App\Models\RosterPattern;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local-only DEMO employees and shifts so the roster and attendance screens can be reviewed.
 * Shift names and times are placeholders (marked DEMO), not the lodge's real hours.
 */
class DemoStaffSeeder extends Seeder
{
    /**
     * Demo roles rostered on Saturday and Sunday as well as Monday to Friday.
     */
    public const WEEKEND_ROLES = [StaffRole::Receptionist, StaffRole::HousekeepingWorker];

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoStaffSeeder may only run in the local environment.');
        }

        $day = Shift::query()->firstOrCreate(['name' => 'DEMO Day shift'], ['starts_at' => '07:00', 'ends_at' => '15:00', 'is_demo' => true]);
        $late = Shift::query()->firstOrCreate(['name' => 'DEMO Late shift'], ['starts_at' => '14:00', 'ends_at' => '22:00', 'is_demo' => true]);

        $roles = [StaffRole::Receptionist, StaffRole::HousekeepingWorker, StaffRole::MaintenanceWorker, StaffRole::Inspector];

        foreach ($roles as $index => $role) {
            $user = User::query()->role($role->value)->where('email', 'like', '%@baseline-lodge.test')->first();

            if ($user === null) {
                continue;
            }

            $employee = Employee::query()->firstOrCreate(['user_id' => $user->id], [
                'full_name' => $user->name,
                'job_title' => $role->label(),
                'employee_number' => 'DEMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'device_person_id' => (string) (101 + $index),
                'status' => EmployeeStatus::Active,
                'starts_on' => '2026-01-01',
                'is_demo' => true,
            ]);

            $shift = $index % 2 === 0 ? $day : $late;

            // A new employee gets Monday to Friday; an existing roster is left as staff set it.
            if (! $employee->rosterPatterns()->exists()) {
                foreach ([1, 2, 3, 4, 5] as $weekday) {
                    $this->addDay($employee, $shift, $weekday);
                }
            }

            // Front desk and housekeeping also work weekends (client request, 24 Nov 2026).
            if (in_array($role, self::WEEKEND_ROLES, true)) {
                foreach ([6, 7] as $weekday) {
                    if (! $employee->rosterPatterns()->where('weekday', $weekday)->exists()) {
                        $this->addDay($employee, $shift, $weekday);
                    }
                }
            }
        }
    }

    private function addDay(Employee $employee, Shift $shift, int $weekday): void
    {
        RosterPattern::query()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'weekday' => $weekday,
            'effective_from' => '2026-01-01',
        ]);
    }
}
