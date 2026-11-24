<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\Employee;
use App\Models\RosterPattern;
use App\Services\Staff\Roster;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function (): void {
    app()['env'] = 'local';

    foreach ([StaffRole::Receptionist, StaffRole::HousekeepingWorker, StaffRole::MaintenanceWorker, StaffRole::Inspector] as $role) {
        staffUser($role)->forceFill(['email' => $role->value.'@baseline-lodge.test', 'name' => 'Demo '.$role->label()])->save();
    }
});

it('EMP-02 rosters demo front desk and housekeeping on Saturday and Sunday as well as weekdays', function (): void {
    $this->seed(DemoStaffSeeder::class);
    $roster = app(Roster::class);
    $saturday = CarbonImmutable::parse('2027-06-05', 'Africa/Johannesburg');
    $monday = $saturday->addDays(2);

    $working = fn (string $job, CarbonImmutable $date): bool => $roster->dayFor(Employee::query()->where('job_title', $job)->sole(), $date)->isWorking();

    expect($working(StaffRole::Receptionist->label(), $saturday))->toBeTrue()
        ->and($working(StaffRole::HousekeepingWorker->label(), $saturday->addDay()))->toBeTrue()
        ->and($working(StaffRole::Receptionist->label(), $monday))->toBeTrue()
        ->and($working(StaffRole::MaintenanceWorker->label(), $saturday))->toBeFalse()
        ->and($working(StaffRole::MaintenanceWorker->label(), $monday))->toBeTrue();
});

it('EMP-02 adds the weekend to an existing demo roster without undoing staff changes or doubling days', function (): void {
    $this->seed(DemoStaffSeeder::class);
    $receptionist = Employee::query()->where('job_title', StaffRole::Receptionist->label())->sole();
    $receptionist->rosterPatterns()->where('weekday', 3)->delete();

    $this->seed(DemoStaffSeeder::class);

    expect($receptionist->rosterPatterns()->orderBy('weekday')->pluck('weekday')->all())->toBe([1, 2, 4, 5, 6, 7])
        ->and(RosterPattern::query()->count())->toBe(6 + 7 + 5 + 5);
});
