<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Creates the first Owner on a new server (no demo users exist outside local development).
 * The password is random and shown once: the owner changes it at first sign-in, and /admin
 * requires two-factor authentication to be set up before it opens.
 */
class CreateOwner extends Command
{
    protected $signature = 'app:create-owner
        {email : The owner\'s email address (their sign-in)}
        {name : The owner\'s full name}';

    protected $description = 'Create the first Owner account with a one-time password (run once on a new server)';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = trim((string) $this->argument('name'));

        if (Validator::make(['email' => $email, 'name' => $name], ['email' => ['required', 'email'], 'name' => ['required', 'max:255']])->fails()) {
            $this->error('Give a valid email address and a name.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('A user with that email already exists. Nothing was changed.');

            return self::FAILURE;
        }

        $password = Str::password(20, symbols: false);

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);
        $user->syncRoles([StaffRole::Owner->value]);

        activity('security')->performedOn($user)->event('owner_created')->log('Owner account created from the command line');

        $this->info("Owner {$email} created.");
        $this->line("One-time password: {$password}");
        $this->warn('Sign in at /admin, change this password straight away and set up two-factor authentication. It is not shown again.');

        return self::SUCCESS;
    }
}
