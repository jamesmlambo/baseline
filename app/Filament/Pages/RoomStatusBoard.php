<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\InspectionStatus;
use App\Enums\RoomCleaningStatus;
use App\Enums\StaffRole;
use App\Filament\Actions\PaymentActions;
use App\Models\Room;
use App\Models\Task;
use App\Services\Housekeeping\Inspections;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Which rooms are ready for the next guest (HK-06), with the open cleaning task and inspection.
 */
class RoomStatusBoard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Room status';

    protected static ?string $title = 'Room status';

    protected static ?string $slug = 'room-status';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.room-status-board';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            StaffRole::Administrator->value, StaffRole::Owner->value, StaffRole::Manager->value, StaffRole::Receptionist->value,
        ]) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $notReady = Room::query()->active()->where('cleaning_status', '<>', RoomCleaningStatus::Ready)->count();

        return $notReady > 0 ? (string) $notReady : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->poll('30s')
            ->query(fn (): Builder => Room::query()->active()->with([
                'accommodationType',
                'tasks' => fn ($query) => $query->open()->with('assignee')->latest('id'),
            ])->withExists(['tasks as has_pending_inspection' => fn (Builder $query) => $query->whereHas('inspection', fn (Builder $inspection) => $inspection->where('status', InspectionStatus::Pending))]))
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Room')->weight('bold')->description(fn (Room $record): string => $record->accommodationType->name),
                TextColumn::make('cleaning_status')->label('Status')->badge()
                    ->formatStateUsing(fn (RoomCleaningStatus $state): string => $state->label())
                    ->color(fn (RoomCleaningStatus $state): string => $state->color()),
                TextColumn::make('cleaning_status_changed_at')->label('Since')->since()->placeholder('—'),
                TextColumn::make('open_task')->label('Open task')->wrap()
                    ->state(fn (Room $record): string => ($task = $record->tasks->first()) instanceof Task
                        ? $task->type->label().' · '.($task->assignee?->name ?? 'not given to anyone').' · '.$task->status->label()
                        : '—'),
                TextColumn::make('has_pending_inspection')->label('Inspection')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Waiting' : '—')
                    ->color(fn (bool $state): ?string => $state ? 'warning' : null),
            ])
            ->filters([
                SelectFilter::make('cleaning_status')->label('Status')->options(RoomCleaningStatus::options()),
            ])
            ->recordActions([
                Action::make('markReady')
                    ->label('Mark ready (owner)')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('warning')
                    ->visible(fn (Room $record): bool => $record->cleaning_status !== RoomCleaningStatus::Ready)
                    ->authorize(fn (Room $record): bool => auth()->user()->can('overrideReadiness', $record))
                    ->schema([Textarea::make('reason')->label('Why is it ready without an inspection?')->required()->rows(2)])
                    ->modalDescription('Recorded in the audit log with your name (PEND-06).')
                    ->action(fn (Room $record, array $data) => PaymentActions::run(fn () => app(Inspections::class)->overrideReady($record, auth()->user(), $data['reason']), 'Room marked ready')),
            ]);
    }
}
