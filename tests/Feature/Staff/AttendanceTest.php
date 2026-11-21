<?php

declare(strict_types=1);

require_once __DIR__.'/StaffTestHelpers.php';

use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\PunchDirection;
use App\Enums\StaffRole;
use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use App\Models\RosterChange;
use App\Notifications\StaffAlert;
use App\Services\Staff\Attendance\AttendanceCalculator;
use App\Services\Staff\Attendance\AttendanceCorrections;
use App\Services\Staff\Attendance\AttendanceExport;
use App\Services\Staff\Attendance\CsvAttendanceSource;
use App\Services\Staff\Attendance\HikvisionAttendanceSource;
use App\Services\Staff\Attendance\PunchRecorder;
use App\Services\Staff\Roster;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    Mail::fake();
    // Monday 14 June 2027, 23:30 — the day's 08:00–16:00 window (closes 19:00) is over.
    $this->travelTo(sast('2027-06-14 23:30'));
});

function derived(Employee $employee, string $date = '2027-06-14'): ?AttendanceDay
{
    return app(AttendanceCalculator::class)->derive($employee, sast($date));
}

// --- Derived statuses (ATT-03, EMP-04) --------------------------------------------------

it('ATT-03 marks a rostered day worked when the employee clocks in on time and out', function (): void {
    $employee = activeEmployee();
    rosterOn($employee, dayShift());
    punch($employee, '2027-06-14 07:55', PunchDirection::In);
    punch($employee, '2027-06-14 16:10', PunchDirection::Out);

    $day = derived($employee);

    expect($day->status)->toBe(AttendanceStatus::Worked)
        ->and($day->minutes_worked)->toBe(495)
        ->and($day->late_minutes)->toBe(0);
});

it('ATT-03 marks late arrivals after the grace period', function (): void {
    $employee = activeEmployee();
    rosterOn($employee, dayShift(lateAfter: 5));
    punch($employee, '2027-06-14 08:20');
    punch($employee, '2027-06-14 16:00');

    $day = derived($employee);

    expect($day->status)->toBe(AttendanceStatus::Late)->and($day->late_minutes)->toBe(20);
});

it('ATT-03 marks a rostered day with no punches absent once the window has closed', function (): void {
    $employee = activeEmployee();
    rosterOn($employee, dayShift());

    expect(derived($employee)->status)->toBe(AttendanceStatus::Absent);
});

it('ATT-03 does not decide a rostered day before its clock-out window closes', function (): void {
    $this->travelTo(sast('2027-06-14 17:00'));
    $employee = activeEmployee();
    rosterOn($employee, dayShift());

    expect(derived($employee))->toBeNull();
});

it('ATT-03 marks a day with only a clock-in incomplete', function (): void {
    $employee = activeEmployee();
    rosterOn($employee, dayShift());
    punch($employee, '2027-06-14 07:58', PunchDirection::In);

    expect(derived($employee)->status)->toBe(AttendanceStatus::Incomplete);
});

it('ATT-03 ignores punches outside the shift window', function (): void {
    $employee = activeEmployee();
    rosterOn($employee, dayShift());
    punch($employee, '2027-06-14 05:00');
    punch($employee, '2027-06-14 21:00');

    expect(derived($employee)->status)->toBe(AttendanceStatus::Absent);
});

it('ATT-03 counts a night shift that runs past midnight on the day it starts', function (): void {
    $this->travelTo(sast('2027-06-15 12:00'));
    $employee = activeEmployee();
    rosterOn($employee, dayShift('22:00', '06:00'));
    punch($employee, '2027-06-14 21:55');
    punch($employee, '2027-06-15 06:05');

    $day = derived($employee, '2027-06-14');

    expect($day->status)->toBe(AttendanceStatus::Worked)->and($day->minutes_worked)->toBe(490);
});

it('EMP-04 never marks off-duty staff absent', function (): void {
    $employee = activeEmployee();
    rosterOn($employee, dayShift(), weekdays: [2, 3, 4, 5, 6]);

    expect(derived($employee)->status)->toBe(AttendanceStatus::OffDuty);
});

it('EMP-04 treats approved leave and public holidays as off duty', function (): void {
    $onLeave = activeEmployee();
    rosterOn($onLeave, dayShift());
    LeaveRequest::query()->create(['employee_id' => $onLeave->id, 'type' => LeaveType::Annual, 'starts_on' => '2027-06-14', 'ends_on' => '2027-06-16', 'status' => LeaveStatus::Approved]);

    $holiday = activeEmployee(name: 'Sipho Demo');
    rosterOn($holiday, dayShift(), worksPublicHolidays: false);
    PublicHoliday::query()->create(['date' => '2027-06-14', 'name' => 'Test holiday']);

    expect(derived($onLeave)->status)->toBe(AttendanceStatus::OffDuty)
        ->and(derived($holiday)->status)->toBe(AttendanceStatus::OffDuty);
});

it('ATT-03 flags punches on a day off for review', function (): void {
    $employee = activeEmployee();
    punch($employee, '2027-06-14 09:00');
    punch($employee, '2027-06-14 13:00');

    expect(derived($employee)->status)->toBe(AttendanceStatus::PendingReview);
});

it('EMP-02 applies roster changes: a day off, and a cover shift', function (): void {
    $regular = activeEmployee();
    rosterOn($regular, dayShift());
    $cover = activeEmployee(name: 'Cover Demo');
    $shift = dayShift('09:00', '17:00');
    RosterChange::query()->create(['employee_id' => $regular->id, 'work_date' => '2027-06-14', 'shift_id' => null, 'reason' => 'Swapped day']);
    RosterChange::query()->create(['employee_id' => $cover->id, 'work_date' => '2027-06-14', 'shift_id' => $shift->id, 'covering_for_employee_id' => $regular->id, 'reason' => 'Cover']);

    $coverDay = app(Roster::class)->dayFor($cover, sast('2027-06-14'));

    expect(derived($regular)->status)->toBe(AttendanceStatus::OffDuty)
        ->and($coverDay->isWorking())->toBeTrue()
        ->and($coverDay->coveringFor->id)->toBe($regular->id)
        ->and(derived($cover)->status)->toBe(AttendanceStatus::Absent);
});

// --- Imports (ATT-01, ATT-02) -----------------------------------------------------------

it('ATT-02 imports a CSV once: importing it again adds nothing', function (): void {
    $employee = activeEmployee(clockId: '1001');
    rosterOn($employee, dayShift());
    $path = csvFile("Employee ID,Date Time,Attendance Status\n1001,2027-06-14 07:59:00,Check-in\n1001,2027-06-14 16:02:00,Check-out\n9999,2027-06-14 08:00:00,Check-in\n,2027-06-14 08:00,\n");
    $manager = staffUser(StaffRole::Manager);

    $first = app(PunchRecorder::class)->import(new CsvAttendanceSource($path), $manager, 'export.csv');
    $second = app(PunchRecorder::class)->import(new CsvAttendanceSource($path), $manager, 'export.csv');

    expect([$first->rows_added, $first->rows_duplicate, $first->rows_failed])->toBe([3, 0, 1])
        ->and([$second->rows_added, $second->rows_duplicate])->toBe([0, 3])
        ->and(AttendancePunch::query()->count())->toBe(3)
        ->and(AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', '2027-06-14')->sole()->status)->toBe(AttendanceStatus::Worked);
});

it('ATT-02 links earlier unmatched punches when an employee gets their clock ID', function (): void {
    $path = csvFile("person_id,timestamp\n2002,2027-06-14 08:00\n2002,2027-06-14 16:00\n");
    app(PunchRecorder::class)->import(new CsvAttendanceSource($path), staffUser(StaffRole::Manager));
    $employee = activeEmployee();
    rosterOn($employee, dayShift());

    $employee->update(['device_person_id' => '2002']);
    $linked = app(PunchRecorder::class)->linkUnmatched($employee);

    expect($linked)->toBe(2)
        ->and(AttendancePunch::query()->whereNull('employee_id')->count())->toBe(0)
        ->and(AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', '2027-06-14')->sole()->status)->toBe(AttendanceStatus::Worked);
});

it('ATT-02 keeps raw punches unchangeable', function (): void {
    $punch = punch(activeEmployee(), '2027-06-14 08:00');

    expect(fn () => $punch->update(['punched_at' => sast('2027-06-14 07:00')]))->toThrow(LogicException::class)
        ->and(fn () => $punch->delete())->toThrow(LogicException::class);
});

it('ATT-01 reports that the Hikvision terminal is not connected yet', function (): void {
    expect(fn () => app(PunchRecorder::class)->import(new HikvisionAttendanceSource, staffUser(StaffRole::Manager)))
        ->toThrow(RuntimeException::class, 'not connected');
});

it('ATT-01 lets only managers import or add punches by hand, with a reason', function (): void {
    $employee = activeEmployee();

    expect(fn () => app(PunchRecorder::class)->manual($employee, sast('2027-06-14 08:00'), PunchDirection::In, 'Terminal down', staffUser(StaffRole::Receptionist)))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(PunchRecorder::class)->manual($employee, sast('2027-06-14 08:00'), PunchDirection::In, ' ', staffUser(StaffRole::Manager)))
        ->toThrow(InvalidArgumentException::class);

    $punch = app(PunchRecorder::class)->manual($employee, sast('2027-06-14 08:00'), PunchDirection::In, 'Terminal down', staffUser(StaffRole::Manager));

    expect($punch->reason)->toBe('Terminal down')
        ->and(Activity::query()->where('event', 'manual_punch')->count())->toBe(1);
});

// --- Corrections (ATT-04) ---------------------------------------------------------------

it('ATT-04 corrects a day with a reason, logs before and after, and keeps it from being recalculated', function (): void {
    $employee = activeEmployee();
    rosterOn($employee, dayShift());
    $day = derived($employee);

    app(AttendanceCorrections::class)->correct($day, AttendanceStatus::Worked, sast('2027-06-14 08:00'), sast('2027-06-14 16:00'), 'Clocked in on paper — terminal offline', staffUser(StaffRole::Manager));

    $log = Activity::query()->where('event', 'attendance_corrected')->sole();
    expect($day->fresh()->is_corrected)->toBeTrue()
        ->and($log->properties['before']['status'])->toBe('absent')
        ->and($log->properties['after']['status'])->toBe('worked')
        ->and(derived($employee)->status)->toBe(AttendanceStatus::Worked)
        ->and(derived($employee)->minutes_worked)->toBe(480);
});

it('ATT-04 refuses corrections without permission or a reason, and to one\'s own record', function (): void {
    $managerUser = staffUser(StaffRole::Manager);
    $employee = activeEmployee($managerUser);
    rosterOn($employee, dayShift());
    $day = derived($employee);

    expect(fn () => app(AttendanceCorrections::class)->correct($day, AttendanceStatus::Worked, null, null, 'x', staffUser(StaffRole::Receptionist)))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AttendanceCorrections::class)->correct($day, AttendanceStatus::Worked, null, null, ' ', staffUser(StaffRole::Owner)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(AttendanceCorrections::class)->correct($day, AttendanceStatus::Worked, null, null, 'Mine', $managerUser))->toThrow(AuthorizationException::class);
});

// --- Export (ATT-05) --------------------------------------------------------------------

it('ATT-05 exports a month as CSV, safe to open in a spreadsheet', function (): void {
    $employee = activeEmployee(name: '=HYPERLINK("x")');
    rosterOn($employee, dayShift());
    punch($employee, '2027-06-14 08:00');
    punch($employee, '2027-06-14 16:30');
    derived($employee);

    $csv = app(AttendanceExport::class)->month(2027, 6, staffUser(StaffRole::Owner));
    $lines = array_values(array_filter(explode("\n", $csv)));

    expect($lines[0])->toStartWith('"Employee number",Name')
        ->and($lines)->toHaveCount(2)
        ->and($lines[1])->toContain("'=HYPERLINK")->toContain('8:30')->toContain('Worked')
        ->and(Activity::query()->where('event', 'attendance_exported')->count())->toBe(1);
});

it('ATT-05 does not let reception export attendance', function (): void {
    expect(fn () => app(AttendanceExport::class)->month(2027, 6, staffUser(StaffRole::Receptionist)))->toThrow(AuthorizationException::class);
});

// --- Off-duty notifications (EMP-04) ----------------------------------------------------

it('EMP-04 holds back non-urgent notices from off-duty staff but still sends urgent ones', function (): void {
    $this->travelTo(sast('2027-06-14 20:00'));
    $user = staffUser(StaffRole::HousekeepingWorker);
    rosterOn(activeEmployee($user), dayShift());

    $normal = new StaffAlert('Low stock', 'Coffee', 'https://example.test');
    $urgent = new StaffAlert('Fault', 'Geyser burst', 'https://example.test', urgent: true);

    expect($normal->via($user))->toBe([])
        ->and($urgent->via($user))->toBe(['mail']);

    $this->travelTo(sast('2027-06-14 10:00'));
    expect($normal->via($user))->toBe(['mail']);
});

it('EMP-04 still notifies people who have no employee profile', function (): void {
    Notification::fake();

    expect((new StaffAlert('x', 'y', 'z'))->via(staffUser(StaffRole::Owner)))->toBe(['mail']);
});
