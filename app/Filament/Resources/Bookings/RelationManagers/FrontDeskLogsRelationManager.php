<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Enums\FrontDeskLogType;
use App\Models\FrontDeskLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Front-desk log for a booking (REC-05): keys, items issued, complaints and notes.
 * Entries are never edited or deleted; complaints and lost keys are resolved.
 */
class FrontDeskLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'frontDeskLogs';

    protected static ?string $title = 'Front-desk log';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('recorder'))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Nothing logged yet')
            ->emptyStateDescription('Use "Front desk › Log key / items / complaint" above.')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y H:i'),
                TextColumn::make('type')->badge()
                    ->formatStateUsing(fn (FrontDeskLogType $state): string => $state->label())
                    ->color(fn (FrontDeskLogType $state): string => $state->color()),
                TextColumn::make('item')->placeholder('—'),
                TextColumn::make('quantity'),
                TextColumn::make('details')->wrap()->placeholder('—'),
                TextColumn::make('recorder.name')->label('By'),
                TextColumn::make('resolved_at')
                    ->label('Resolved')
                    ->dateTime('j M H:i')
                    ->placeholder(fn (FrontDeskLog $record): string => $record->type->needsResolution() ? 'Open' : '—'),
            ])
            ->recordActions([
                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check')
                    ->visible(fn (FrontDeskLog $record): bool => $record->type->needsResolution() && $record->resolved_at === null)
                    ->authorize(fn (): bool => auth()->user()->can('update', $this->getOwnerRecord()))
                    ->requiresConfirmation()
                    ->action(function (FrontDeskLog $record): void {
                        $record->forceFill(['resolved_at' => now(), 'resolved_by' => auth()->id()])->save();
                        Notification::make()->title('Resolved')->success()->send();
                    }),
            ]);
    }
}
