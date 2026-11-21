<?php

declare(strict_types=1);

namespace App\Filament\Resources\Inspections;

use App\Enums\InspectionStatus;
use App\Filament\Actions\HousekeepingActions;
use App\Filament\Resources\Inspections\Pages\ListInspections;
use App\Models\Inspection;
use App\Models\InspectionScore;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Inspections of finished cleans (HK-05). Inspectors usually score them in /staff.
 */
class InspectionResource extends Resource
{
    protected static ?string $model = Inspection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 15;

    public static function getNavigationBadge(): ?string
    {
        $pending = Inspection::query()->pending()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['room', 'task.completer', 'inspector']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('room.name')->label('Room')->weight('bold')->placeholder('—'),
                TextColumn::make('task.title')->label('Clean')->wrap(),
                TextColumn::make('task.completer.name')->label('Cleaned by')->placeholder('—'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (InspectionStatus $state): string => $state->label())
                    ->color(fn (InspectionStatus $state): string => $state->color()),
                TextColumn::make('inspector.name')->label('Inspector')->placeholder('—'),
                TextColumn::make('inspected_at')->label('Inspected')->dateTime('j M H:i', 'Africa/Johannesburg')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(InspectionStatus::options())->default(InspectionStatus::Pending->value),
            ])
            ->recordActions([
                HousekeepingActions::inspect(),
                Action::make('results')
                    ->label('Results')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (Inspection $record): bool => $record->status !== InspectionStatus::Pending)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema(fn (Inspection $record): array => [
                        TextEntry::make('scores')->hiddenLabel()->listWithLineBreaks()->state(
                            $record->scores()->get()->map(fn (InspectionScore $score): string => ($score->passed ? '✓ ' : '✗ ')."{$score->criterion}: {$score->score}/{$record->scale_max}".($score->comment ? " — {$score->comment}" : ''))->all(),
                        ),
                        TextEntry::make('notes')->state($record->notes)->placeholder('—'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInspections::route('/'),
        ];
    }
}
