<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Enums\StaffRole;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('roles'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => StaffRole::tryFrom($state)?->label() ?? $state),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                IconColumn::make('app_authentication_secret')
                    ->label('2FA')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->app_authentication_secret)),
                TextColumn::make('last_login_at')
                    ->label('Last sign-in')
                    ->dateTime('j M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')
                    ->options(StaffRole::options())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'])
                        ? $query->role($data['value'])
                        : $query),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
