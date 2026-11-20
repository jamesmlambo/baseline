<?php

declare(strict_types=1);

use App\Enums\StaffRole;

it('requires two-factor authentication for privileged roles', function (): void {
    expect(StaffRole::rolesRequiringTwoFactor())->toContain(
        StaffRole::Administrator,
        StaffRole::Owner,
        StaffRole::Manager,
        StaffRole::Receptionist,
    );
});

it('keeps field workers out of the admin panel', function (): void {
    expect(StaffRole::adminPanelRoles())
        ->not->toContain(StaffRole::HousekeepingWorker)
        ->not->toContain(StaffRole::MaintenanceWorker)
        ->not->toContain(StaffRole::Inspector);
});
