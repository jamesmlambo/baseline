<?php

declare(strict_types=1);

require_once __DIR__.'/StaffTestHelpers.php';

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\StaffRole;
use App\Filament\Resources\Employees\Pages\ManageEmployees;
use App\Filament\Staff\Resources\MyLeave\MyLeaveResource;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Notifications\StaffAlert;
use App\Services\Staff\Attendance\AttendanceCalculator;
use App\Services\Staff\EmployeeProfiles;
use App\Services\Staff\LeaveManager;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    Mail::fake();
    $this->travelTo(sast('2027-06-14 10:00'));
});

// --- Leave (EMP-02) ---------------------------------------------------------------------

it('EMP-02 lets staff ask for leave and tells managers', function (): void {
    Notification::fake();
    $manager = staffUser(StaffRole::Manager);
    $user = staffUser(StaffRole::HousekeepingWorker);
    $employee = activeEmployee($user);

    $leave = app(LeaveManager::class)->request($employee, LeaveType::Annual, sast('2027-07-01'), sast('2027-07-03'), 'Family visit', $user);

    expect($leave->status)->toBe(LeaveStatus::Pending)->and($leave->days())->toBe(3);
    Notification::assertSentTo($manager, StaffAlert::class);
});

it('EMP-02 refuses overlapping leave and leave asked for someone else', function (): void {
    $user = staffUser(StaffRole::HousekeepingWorker);
    $employee = activeEmployee($user);
    app(LeaveManager::class)->request($employee, LeaveType::Annual, sast('2027-07-01'), sast('2027-07-03'), null, $user);

    expect(fn () => app(LeaveManager::class)->request($employee, LeaveType::Sick, sast('2027-07-03'), sast('2027-07-04'), null, $user))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(LeaveManager::class)->request(activeEmployee(name: 'Other'), LeaveType::Annual, sast('2027-07-10'), sast('2027-07-10'), null, $user))
        ->toThrow(AuthorizationException::class);
});

it('EMP-02 lets a manager approve leave, which turns a past absence into off duty', function (): void {
    $this->travelTo(sast('2027-06-15 10:00'));
    $employee = activeEmployee(staffUser(StaffRole::HousekeepingWorker));
    rosterOn($employee, dayShift());
    app(AttendanceCalculator::class)->derive($employee, sast('2027-06-14'));
    $leave = app(LeaveManager::class)->request($employee, LeaveType::Sick, sast('2027-06-14'), sast('2027-06-14'), 'Flu', staffUser(StaffRole::Manager));

    app(LeaveManager::class)->decide($leave, true, null, staffUser(StaffRole::Manager));

    expect($leave->fresh()->status)->toBe(LeaveStatus::Approved)
        ->and(AttendanceDay::query()->where('employee_id', $employee->id)->sole()->status)->toBe(AttendanceStatus::OffDuty);
});

it('EMP-02 does not let a manager approve their own leave, and a decline needs a reason', function (): void {
    $managerUser = staffUser(StaffRole::Manager);
    $leave = app(LeaveManager::class)->request(activeEmployee($managerUser), LeaveType::Annual, sast('2027-07-01'), sast('2027-07-02'), null, $managerUser);

    expect(fn () => app(LeaveManager::class)->decide($leave, true, null, $managerUser))->toThrow(AuthorizationException::class)
        ->and(fn () => app(LeaveManager::class)->decide($leave, false, null, staffUser(StaffRole::Owner)))->toThrow(InvalidArgumentException::class);

    app(LeaveManager::class)->decide($leave, true, null, staffUser(StaffRole::Owner));

    expect($leave->fresh()->status)->toBe(LeaveStatus::Approved);
});

// --- Profiles (EMP-01) ------------------------------------------------------------------

it('EMP-01 holds an employee\'s own changes until a manager approves them', function (): void {
    $user = staffUser(StaffRole::HousekeepingWorker);
    $employee = activeEmployee($user);

    app(EmployeeProfiles::class)->submit($employee, ['phone' => '082 000 1111', 'full_name' => 'Hacked Name'], null, $user);

    expect($employee->fresh()->phone)->toBeNull()
        ->and($employee->fresh()->pending_changes)->toBe(['phone' => '082 000 1111']);

    app(EmployeeProfiles::class)->approve($employee->fresh(), staffUser(StaffRole::Manager));

    expect($employee->fresh()->phone)->toBe('082 000 1111')
        ->and($employee->fresh()->full_name)->toBe('Thandi Demo')
        ->and($employee->fresh()->pending_changes)->toBeNull();
});

it('EMP-01 activates a new profile on approval and stores contact details encrypted', function (): void {
    $employee = Employee::query()->create(['full_name' => 'New Starter', 'phone' => '083 123 4567']);

    app(EmployeeProfiles::class)->approve($employee, staffUser(StaffRole::Owner));

    expect($employee->fresh()->status)->toBe(EmployeeStatus::Active)
        ->and(DB::table('employees')->where('id', $employee->id)->value('phone'))->not->toContain('083 123 4567');
});

it('EMP-01 lets staff change only their own profile', function (): void {
    $employee = activeEmployee(staffUser(StaffRole::HousekeepingWorker));

    expect(fn () => app(EmployeeProfiles::class)->submit($employee, ['phone' => '1'], null, staffUser(StaffRole::HousekeepingWorker)))
        ->toThrow(AuthorizationException::class);
});

// --- Access (EMP-03) --------------------------------------------------------------------

it('EMP-03 keeps HR screens to the owner and managers', function (StaffRole $role, bool $allowed): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staffUser($role));

    $screen = Livewire::test(ManageEmployees::class);

    $allowed ? $screen->assertOk() : $screen->assertForbidden();
})->with([
    'owner' => [StaffRole::Owner, true],
    'manager' => [StaffRole::Manager, true],
    'receptionist' => [StaffRole::Receptionist, false],
    'administrator' => [StaffRole::Administrator, false],
    'content editor' => [StaffRole::ContentEditor, false],
]);

it('EMP-03 keeps field staff out of the admin HR screens', function (): void {
    $this->actingAs(staffUser(StaffRole::HousekeepingWorker))->get('/admin/employees')->assertForbidden();
});

it('EMP-03 shows staff only their own leave and attendance', function (): void {
    $user = staffUser(StaffRole::HousekeepingWorker);
    $mine = activeEmployee($user);
    $other = activeEmployee(staffUser(StaffRole::HousekeepingWorker), 'Other Person');
    app(LeaveManager::class)->request($mine, LeaveType::Annual, sast('2027-07-01'), sast('2027-07-01'), 'Mine', $user);
    app(LeaveManager::class)->request($other, LeaveType::Annual, sast('2027-07-01'), sast('2027-07-01'), 'Theirs', staffUser(StaffRole::Manager));

    $this->actingAs($user)
        ->get('/staff/my-leave')
        ->assertOk();

    expect(MyLeaveResource::getEloquentQuery()->pluck('reason')->all())->toBe(['Mine']);
});
