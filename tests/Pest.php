<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests run against PostgreSQL (baselineCL_testing) so constraint and
| concurrency behaviour matches production. Never switch these to SQLite.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => test()->seed(RoleSeeder::class))
    ->in('Feature');

function staffUser(StaffRole ...$roles): User
{
    $user = User::factory()->create();
    $user->syncRoles(array_map(fn (StaffRole $role): string => $role->value, $roles));

    return $user;
}
