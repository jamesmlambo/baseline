<?php

declare(strict_types=1);

namespace App\Filament\Staff\Resources\MyTasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Actions\HousekeepingActions;
use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Staff\Resources\MyTasks\Pages\ListMyTasks;
use App\Models\Task;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * A worker's own tasks (HK-02): start, tick the checklist, add photos, finish. Big buttons for phones.
 */
class MyTaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $slug = 'my-tasks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'My tasks';

    protected static ?string $modelLabel = 'task';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('assigned_to', auth()->id());
    }

    public static function getNavigationBadge(): ?string
    {
        $open = Task::query()->open()->where('assigned_to', auth()->id())->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('60s')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['room', 'asset'])
                ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")
                ->orderByRaw('due_at is null, due_at'))
            ->columns([
                TextColumn::make('title')->weight('bold')->wrap()
                    ->description(fn (Task $record): string => $record->locationLabel().($record->estimated_minutes ? " · about {$record->estimated_minutes} min" : '')),
                TextColumn::make('priority')->badge()->formatStateUsing(fn (TaskPriority $state): string => $state->label())->color(fn (TaskPriority $state): string => $state->color()),
                TextColumn::make('status')->badge()->formatStateUsing(fn (TaskStatus $state): string => $state->label())->color(fn (TaskStatus $state): string => $state->color()),
                TextColumn::make('due_at')->label('Due')->dateTime('D H:i', 'Africa/Johannesburg')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->multiple()->options(TaskStatus::options())
                    ->default([TaskStatus::Open->value, TaskStatus::InProgress->value]),
            ])
            ->recordActions([
                HousekeepingActions::start(),
                HousekeepingActions::complete(),
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema(fn (Schema $schema): Schema => TaskResource::infolist($schema)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMyTasks::route('/'),
        ];
    }
}
