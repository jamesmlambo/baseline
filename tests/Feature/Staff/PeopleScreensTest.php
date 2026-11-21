<?php

declare(strict_types=1);

require_once __DIR__.'/StaffTestHelpers.php';

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Enums\LeaveStatus;
use App\Enums\StaffRole;
use App\Filament\Pages\RosterWeek;
use App\Filament\Resources\AttendanceDays\Pages\ListAttendanceDays;
use App\Filament\Resources\Employees\Pages\ManageEmployees;
use App\Filament\Resources\RosterPatterns\Pages\ManageRosterPatterns;
use App\Filament\Staff\Pages\MyProfile;
use App\Filament\Staff\Pages\MyShifts;
use App\Filament\Staff\Resources\MyLeave\Pages\ListMyLeave;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\RosterPattern;
use App\Services\Staff\Attendance\AttendanceCalculator;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Mail::fake();
    $this->travelTo(sast('2027-06-14 23:30'));
});

it('EMP-01 lets a manager add an employee and approve a profile', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staffUser(StaffRole::Manager));

    Livewire::test(ManageEmployees::class)
        ->callAction('create', ['full_name' => 'Ayanda Demo', 'status' => EmployeeStatus::Pending->value])
        ->assertHasNoActionErrors();

    $employee = Employee::query()->sole();
    Livewire::test(ManageEmployees::class)
        ->callTableAction('review', $employee)
        ->assertNotified('Approved');

    expect($employee->fresh()->status)->toBe(EmployeeStatus::Active);
});

it('EMP-02 adds several weekdays to the roster in one go', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staffUser(StaffRole::Manager));
    $employee = activeEmployee();
    $shift = dayShift();

    Livewire::test(ManageRosterPatterns::class)
        ->callAction('create', ['employee_id' => $employee->id, 'shift_id' => $shift->id, 'weekdays' => ['1', '3', '5'], 'effective_from' => '2027-06-01', 'works_public_holidays' => true])
        ->assertHasNoActionErrors();

    expect(RosterPattern::query()->where('employee_id', $employee->id)->pluck('weekday')->sort()->values()->all())->toBe([1, 3, 5]);
});

it('EMP-02 shows the week roster', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staffUser(StaffRole::Owner));
    rosterOn(activeEmployee(name: 'Lindiwe Demo'), dayShift(), [1]);

    Livewire::test(RosterWeek::class)->assertSee('Lindiwe Demo')->assertSee('08:00–16:00');
});

it('ATT-02 imports a clock CSV from the attendance screen', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staffUser(StaffRole::Manager));
    $employee = activeEmployee(clockId: '77');
    rosterOn($employee, dayShift());
    Storage::fake('local');
    Storage::disk('local')->put('attendance-imports/clock.csv', "person_id,timestamp,direction\n77,2027-06-14 08:00,in\n77,2027-06-14 16:00,out\n");

    Livewire::test(ListAttendanceDays::class)
        ->callAction('importCsv', ['file' => ['clock' => 'attendance-imports/clock.csv']])
        ->assertHasNoActionErrors()
        ->assertNotified('Import finished');

    expect(AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', '2027-06-14')->sole()->status)->toBe(AttendanceStatus::Worked);
});

it('ATT-04 corrects a day from the attendance screen', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staffUser(StaffRole::Manager));
    $employee = activeEmployee();
    rosterOn($employee, dayShift());
    $day = app(AttendanceCalculator::class)->derive($employee, sast('2027-06-14'));

    Livewire::test(ListAttendanceDays::class)
        ->callTableAction('correct', $day, ['status' => 'worked', 'first_in' => '2027-06-14 08:00', 'last_out' => '2027-06-14 16:00', 'reason' => 'Paper register'])
        ->assertNotified('Attendance corrected');

    expect($day->fresh()->is_corrected)->toBeTrue()->and($day->fresh()->minutes_worked)->toBe(480);
});

it('EMP-03 shows staff their shifts and lets them ask for leave and update details', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('staff'));
    $user = staffUser(StaffRole::HousekeepingWorker);
    $employee = activeEmployee($user);
    rosterOn($employee, dayShift(), [1]);
    $this->actingAs($user);

    Livewire::test(MyShifts::class)->assertSee('Today')->assertSee('08:00–16:00');

    Livewire::test(ListMyLeave::class)
        ->callAction('requestLeave', ['type' => 'annual', 'starts_on' => '2027-07-01', 'ends_on' => '2027-07-02'])
        ->assertNotified('Sent — a manager will reply');

    Livewire::test(MyProfile::class)
        ->fillForm(['phone' => '082 555 0000'])
        ->call('save')
        ->assertNotified('Sent for approval');

    expect(LeaveRequest::query()->sole()->status)->toBe(LeaveStatus::Pending)
        ->and($employee->fresh()->pending_changes)->toBe(['phone' => '082 555 0000']);
});
