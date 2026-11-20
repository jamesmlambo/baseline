<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Spatie\Activitylog\Models\Activity;

it('only lets administrators and owners manage staff accounts', function (StaffRole $role, bool $allowed): void {
    $user = staffUser($role);

    expect($user->can('viewAny', User::class))->toBe($allowed)
        ->and($user->can('create', User::class))->toBe($allowed);
})->with([
    [StaffRole::Administrator, true],
    [StaffRole::Owner, true],
    [StaffRole::Manager, false],
    [StaffRole::Receptionist, false],
    [StaffRole::ContentEditor, false],
    [StaffRole::HousekeepingWorker, false],
]);

it('never allows staff accounts to be deleted', function (): void {
    $owner = staffUser(StaffRole::Owner);
    $other = staffUser(StaffRole::Receptionist);

    expect($owner->can('delete', $other))->toBeFalse();
});

it('stops an administrator from editing an owner account', function (): void {
    $admin = staffUser(StaffRole::Administrator);
    $owner = staffUser(StaffRole::Owner);

    expect($admin->can('update', $owner))->toBeFalse()
        ->and(staffUser(StaffRole::Owner)->can('update', $owner))->toBeTrue();
});

it('records role changes in the audit log', function (): void {
    $admin = staffUser(StaffRole::Administrator);
    $this->actingAs($admin);

    $user = staffUser(StaffRole::Receptionist);
    $user->syncRoles([StaffRole::Manager->value]);

    $events = Activity::query()
        ->where('log_name', 'roles')
        ->where('subject_id', $user->id)
        ->pluck('event')
        ->all();

    expect($events)->toContain('role_attached', 'role_detached');

    $attached = Activity::query()
        ->where('log_name', 'roles')
        ->where('subject_id', $user->id)
        ->where('event', 'role_attached')
        ->latest('id')
        ->first();

    expect($attached->causer_id)->toBe($admin->id)
        ->and($attached->properties['roles'])->toBe([StaffRole::Manager->value]);
});

it('seeds pending client decisions without inventing policy values', function (): void {
    $this->seed(SettingSeeder::class);

    $manualHold = Setting::query()->where('key', 'booking.hold_hours_manual_eft')->first();
    $phone = Setting::query()->where('key', 'contact.phone')->first();

    expect($manualHold->is_pending_client_decision)->toBeTrue()
        ->and($manualHold->value)->toBeNull()
        ->and($phone->value)->toBeNull()
        ->and(Setting::get('payments.method_order'))->toBe(['instant_eft', 'card', 'eft']);
});

it('does not overwrite settings that were already changed when re-seeded', function (): void {
    $this->seed(SettingSeeder::class);
    Setting::query()->where('key', 'escalation.owner_minutes')->update(['value' => 45]);

    $this->seed(SettingSeeder::class);

    expect(Setting::get('escalation.owner_minutes'))->toBe(45);
});

it('only lets administrators and owners edit settings, and nobody create or delete them', function (): void {
    $this->seed(SettingSeeder::class);
    $setting = Setting::query()->first();

    expect(staffUser(StaffRole::Owner)->can('update', $setting))->toBeTrue()
        ->and(staffUser(StaffRole::Manager)->can('update', $setting))->toBeFalse()
        ->and(staffUser(StaffRole::Owner)->can('create', Setting::class))->toBeFalse()
        ->and(staffUser(StaffRole::Owner)->can('delete', $setting))->toBeFalse();
});
