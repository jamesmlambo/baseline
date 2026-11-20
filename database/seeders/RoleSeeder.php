<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StaffRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the staff roles. Safe to run in every environment.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (StaffRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }
}
