<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Filament\Actions\HousekeepingActions;
use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Models\Asset;
use App\Models\Room;
use App\Models\Task;
use App\Models\User;
use App\Services\Housekeeping\TaskManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * All housekeeping, gardening and maintenance tasks (HK-01..03). Workers do their tasks in /staff.
 */
class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        $unassigned = Task::query()->open()->whereNull('assigned_to')->count();

        return $unassigned > 0 ? (string) $unassigned : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Open tasks nobody has been given yet';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->options(fn (): array => collect(TaskType::cases())
                    ->filter(fn (TaskType $type): bool => auth()->user()->can('assignType', [Task::class, $type]))
                    ->mapWithKeys(fn (TaskType $type): array => [$type->value => $type->label()])
                    ->all())
                ->required()
                ->live(),
            TextInput::make('title')->placeholder('Leave empty to use the task type and room')->maxLength(255),
            Select::make('room_id')->label('Room')->options(fn (): array => Room::query()->active()->orderBy('sort_order')->pluck('name', 'id')->all())->searchable(),
            Select::make('asset_id')->label('Item / equipment')->options(fn (): array => Asset::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            TextInput::make('location')->label('Other place')->placeholder('e.g. Braai area, front garden')->maxLength(255),
            Select::make('assigned_to')
                ->label('Give to')
                ->options(fn (Get $get): array => HousekeepingActions::assigneeOptions(filled($get('type')) ? TaskType::from($get('type')) : null))
                ->searchable(),
            Select::make('priority')->options(TaskPriority::options())->default(TaskPriority::Normal->value)->required(),
            DateTimePicker::make('due_at')->label('Due')->seconds(false)->timezone('Africa/Johannesburg'),
            TagsInput::make('checklist')->label('Checklist (leave empty to use the standard one)')->placeholder('Add an item and press Enter')->columnSpanFull(),
            Textarea::make('notes')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('type')->formatStateUsing(fn (TaskType $state): string => $state->label()),
            TextEntry::make('location')->label('Where')->state(fn (Task $record): string => $record->locationLabel()),
            TextEntry::make('assignee.name')->label('Given to')->placeholder('Nobody yet'),
            TextEntry::make('status')->badge()->formatStateUsing(fn (TaskStatus $state): string => $state->label())->color(fn (TaskStatus $state): string => $state->color()),
            TextEntry::make('checklist')->label('Checklist')->columnSpanFull()->listWithLineBreaks()
                ->state(fn (Task $record): array => array_map(fn (array $item): string => ($item['done'] ? '☑ ' : '☐ ').$item['label'], $record->checklistItems()))
                ->placeholder('No checklist'),
            TextEntry::make('notes')->columnSpanFull()->placeholder('—'),
            TextEntry::make('completion_notes')->label('Worker\'s notes')->columnSpanFull()->placeholder('—'),
            TextEntry::make('completer.name')->label('Completed by')->placeholder('—'),
            TextEntry::make('completed_at')->label('Completed')->dateTime('j M Y H:i', 'Africa/Johannesburg')->placeholder('—'),
            TextEntry::make('photos')->label('Photos')->state(fn (Task $record): string => (string) $record->getMedia('photos')->count()),
            TextEntry::make('cost_cents')->label('Cost')->state(fn (Task $record): string => $record->cost_cents?->format() ?? '—'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('30s')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['room', 'asset', 'assignee']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->wrap()
                    ->description(fn (Task $record): string => $record->type->label().($record->source !== Task::SOURCE_MANUAL ? ' · '.str_replace('_', ' ', $record->source) : '')),
                TextColumn::make('where')->label('Where')->state(fn (Task $record): string => $record->locationLabel()),
                TextColumn::make('assignee.name')->label('Given to')->placeholder('Nobody yet')->color(fn (Task $record): ?string => $record->assigned_to === null && $record->status->isOpen() ? 'danger' : null),
                TextColumn::make('priority')->badge()->formatStateUsing(fn (TaskPriority $state): string => $state->label())->color(fn (TaskPriority $state): string => $state->color()),
                TextColumn::make('status')->badge()->formatStateUsing(fn (TaskStatus $state): string => $state->label())->color(fn (TaskStatus $state): string => $state->color()),
                TextColumn::make('due_at')->label('Due')->dateTime('D j M H:i', 'Africa/Johannesburg')->sortable()->placeholder('—'),
                TextColumn::make('estimated_minutes')->label('Est.')->suffix(' min')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->multiple()->options(TaskStatus::options())
                    ->default([TaskStatus::Open->value, TaskStatus::InProgress->value]),
                SelectFilter::make('type')->multiple()->options(TaskType::options()),
                SelectFilter::make('assigned_to')->label('Given to')->options(fn (): array => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                Action::make('assign')
                    ->label(fn (Task $record): string => $record->assigned_to === null ? 'Give to…' : 'Reassign')
                    ->icon('heroicon-o-user-plus')
                    ->authorize(fn (Task $record): bool => auth()->user()->can('assign', $record))
                    ->schema(fn (Task $record): array => [
                        Select::make('assigned_to')->label('Give to')->options(HousekeepingActions::assigneeOptions($record->type))->searchable()->placeholder('Nobody (unassign)'),
                    ])
                    ->fillForm(fn (Task $record): array => ['assigned_to' => $record->assigned_to])
                    ->action(fn (Task $record, array $data) => PaymentActions::run(
                        fn () => app(TaskManager::class)->assign($record, filled($data['assigned_to'] ?? null) ? User::query()->find($data['assigned_to']) : null, auth()->user()),
                        'Task assigned',
                    )),
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema(fn (Schema $schema): Schema => static::infolist($schema)),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->authorize(fn (Task $record): bool => auth()->user()->can('cancel', $record))
                    ->schema([Textarea::make('reason')->required()->rows(2)])
                    ->action(fn (Task $record, array $data) => PaymentActions::run(fn () => app(TaskManager::class)->cancel($record, auth()->user(), $data['reason']), 'Task cancelled')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
        ];
    }
}
