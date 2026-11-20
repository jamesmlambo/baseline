<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local-only demo staff accounts with random passwords.
 * Credentials are written to storage/app/private/dev-credentials.txt,
 * which is not committed to Git.
 */
class DevelopmentUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DevelopmentUserSeeder may only run in the local environment.');
        }

        $lines = [
            'Baseline Lodge — LOCAL DEVELOPMENT ACCOUNTS ONLY. Never use in production.',
            'Generated: '.now()->toDateTimeString(),
            '',
        ];

        foreach (StaffRole::cases() as $role) {
            $email = str_replace('_', '.', $role->value).'@baseline-lodge.test';
            $password = Str::password(20);

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => 'Demo '.$role->label(),
                    'password' => $password,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$role->value]);

            $lines[] = sprintf('%-22s %-45s %s', $role->label(), $email, $password);
        }

        Storage::disk('local')->put('dev-credentials.txt', implode(PHP_EOL, $lines).PHP_EOL);

        $this->command?->info('Demo staff passwords written to storage/app/private/dev-credentials.txt');
    }
}
