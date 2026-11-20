<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\User;
use Filament\Facades\Filament;

it('redirects guests to the admin login', function (): void {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('renders the admin and staff login pages', function (string $path): void {
    $this->get($path)->assertOk()->assertSee('Baseline Lodge');
})->with(['/admin/login', '/staff/login']);

it('lets admin-panel roles into /admin', function (StaffRole $role): void {
    $user = staffUser($role);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
})->with(StaffRole::adminPanelRoles());

it('keeps field roles out of /admin but lets them into /staff', function (StaffRole $role): void {
    $user = staffUser($role);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse()
        ->and($user->canAccessPanel(Filament::getPanel('staff')))->toBeTrue();
})->with([
    StaffRole::HousekeepingWorker,
    StaffRole::MaintenanceWorker,
    StaffRole::Inspector,
]);

it('blocks deactivated accounts from every panel', function (): void {
    $user = User::factory()->inactive()->create();
    $user->assignRole(StaffRole::Owner->value);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse()
        ->and($user->canAccessPanel(Filament::getPanel('staff')))->toBeFalse();
});

it('blocks users without a role from every panel', function (): void {
    $user = User::factory()->create();

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse()
        ->and($user->canAccessPanel(Filament::getPanel('staff')))->toBeFalse();
});

it('requires two-factor setup before an owner can use /admin', function (): void {
    $this->actingAs(staffUser(StaffRole::Owner))
        ->get('/admin')
        ->assertRedirectContains('multi-factor-authentication');
});

it('stores the two-factor secret encrypted', function (): void {
    $user = staffUser(StaffRole::Owner);
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $raw = DB::table('users')->where('id', $user->id)->value('app_authentication_secret');

    expect($raw)->not->toBe('JBSWY3DPEHPK3PXP')
        ->and($user->fresh()->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP');
});
