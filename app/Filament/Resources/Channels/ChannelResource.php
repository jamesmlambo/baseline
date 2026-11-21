<?php

declare(strict_types=1);

namespace App\Filament\Resources\Channels;

use App\Filament\Resources\Channels\Pages\EditChannel;
use App\Filament\Resources\Channels\Pages\ListChannels;
use App\Filament\Resources\Channels\RelationManagers\IcalLinksRelationManager;
use App\Filament\Resources\Channels\RelationManagers\RoomTypesRelationManager;
use App\Filament\Resources\Channels\RelationManagers\SyncRunsRelationManager;
use App\Models\Channel;
use App\Services\Channels\ChannelDrivers;
use App\Services\Channels\ChannelSync;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * External channels (CHN-01/04). Shows honestly whether anything is connected and when it last synced.
 */
class ChannelResource extends Resource
{
    protected static ?string $model = Channel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Bookings';

    protected static ?string $navigationLabel = 'Channels';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            Select::make('driver')->label('Connection')->options(ChannelDrivers::options())->required()
                ->helperText('No channel manager is connected yet (PEND-07/08). The iCal fallback below works without one.'),
            Toggle::make('is_enabled')->label('Include in scheduled syncs'),
            TextInput::make('stale_after_minutes')->label('Warn when not synced for (minutes)')->integer()->minValue(5)->maxValue(10080)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['icalLinks', 'roomTypes']))
            ->columns([
                TextColumn::make('name')->weight('bold'),
                TextColumn::make('health')->label('Status')->badge()
                    ->state(fn (Channel $record): string => match (true) {
                        ! $record->is_enabled => 'Off',
                        $record->driver === 'null' => 'Not connected',
                        $record->isStale() => 'Stale — check',
                        default => 'Syncing',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Syncing' => 'success',
                        'Stale — check' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('last_successful_sync_at')->label('Last good sync')->since()->placeholder('Never'),
                TextColumn::make('last_error')->label('Last error')->wrap()->limit(80)->placeholder('—')->color('danger'),
                TextColumn::make('room_types_count')->label('Room codes'),
                TextColumn::make('ical_links_count')->label('iCal rooms'),
            ])
            ->recordActions([
                Action::make('syncNow')
                    ->label('Sync now')
                    ->icon('heroicon-o-arrow-path')
                    ->authorize(fn (Channel $record): bool => auth()->user()->can('update', $record))
                    ->action(function (Channel $record): void {
                        $run = app(ChannelSync::class)->sync($record);
                        Notification::make()
                            ->title(match ($run->status) {
                                'ok' => "Synced ({$run->items} reservation(s))",
                                'not_connected' => 'Not connected — nothing to sync',
                                default => 'Sync failed',
                            })
                            ->body($run->message)
                            ->color($run->status === 'failed' ? 'danger' : ($run->status === 'ok' ? 'success' : 'gray'))
                            ->send();
                    }),
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RoomTypesRelationManager::class,
            IcalLinksRelationManager::class,
            SyncRunsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChannels::route('/'),
            'edit' => EditChannel::route('/{record}/edit'),
        ];
    }
}
