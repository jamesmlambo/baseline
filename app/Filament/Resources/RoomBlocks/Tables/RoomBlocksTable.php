<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoomBlocks\Tables;

use App\Actions\Inventory\ReleaseRoomBlock;
use App\Enums\RoomBlockReason;
use App\Models\RoomBlock;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoomBlocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('room.accommodationType'))
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('room.name')
                    ->label('Room')
                    ->description(fn (RoomBlock $record): string => $record->room->accommodationType->name)
                    ->searchable(),
                TextColumn::make('starts_on')
                    ->label('From night')
                    ->date('j M Y')
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->label('Back in service')
                    ->date('j M Y'),
                TextColumn::make('reason')
                    ->badge()
                    ->formatStateUsing(fn (RoomBlockReason $state): string => $state->label()),
                TextColumn::make('notes')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('released_at')
                    ->label('Released')
                    ->dateTime('j M Y H:i')
                    ->placeholder('Active'),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Active blocks')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('released_at'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('released_at'),
                    ),
                SelectFilter::make('reason')
                    ->options(RoomBlockReason::options()),
            ])
            ->recordActions([
                Action::make('release')
                    ->label('Release')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('The room becomes bookable again on these nights.')
                    ->authorize(fn (RoomBlock $record): bool => auth()->user()->can('release', $record))
                    ->action(function (RoomBlock $record): void {
                        app(ReleaseRoomBlock::class)->handle($record, auth()->user());

                        Notification::make()->title('Room released')->success()->send();
                    }),
            ]);
    }
}
