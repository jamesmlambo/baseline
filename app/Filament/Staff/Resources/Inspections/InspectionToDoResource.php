<?php

declare(strict_types=1);

namespace App\Filament\Staff\Resources\Inspections;

use App\Enums\InspectionStatus;
use App\Enums\StaffRole;
use App\Filament\Actions\HousekeepingActions;
use App\Filament\Staff\Resources\Inspections\Pages\ListInspectionsToDo;
use App\Models\Inspection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Inspections waiting to be scored (HK-05), for inspectors, managers and the owner.
 */
class InspectionToDoResource extends Resource
{
    protected static ?string $model = Inspection::class;

    protected static ?string $slug = 'inspections';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Inspections';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole([StaffRole::Inspector->value, StaffRole::Manager->value, StaffRole::Owner->value]) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('status', InspectionStatus::Pending);
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = Inspection::query()->pending()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('60s')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['room', 'task.completer']))
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('room.name')->label('Room')->weight('bold')->placeholder('—')
                    ->description(fn (Inspection $record): string => $record->task->title),
                TextColumn::make('task.completer.name')->label('Cleaned by'),
                TextColumn::make('created_at')->label('Waiting since')->since(),
            ])
            ->recordActions([HousekeepingActions::inspect()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInspectionsToDo::route('/'),
        ];
    }
}
