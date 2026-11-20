<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\StaffRole;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(32),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Inactive accounts cannot sign in to any panel.')
                            ->default(true),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->rule(Password::defaults())
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave blank to keep the current password.' : null),
                    ]),
                Section::make('Roles')
                    ->description('A person may hold more than one role. Role changes are recorded in the audit log.')
                    ->schema([
                        CheckboxList::make('role_names')
                            ->hiddenLabel()
                            ->options(StaffRole::options())
                            ->columns(2)
                            ->required(),
                    ]),
            ]);
    }
}
