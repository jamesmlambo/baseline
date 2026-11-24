<?php

declare(strict_types=1);

namespace App\Filament\Resources\MaintenanceSchedules;

use App\Enums\RepeatUnit;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Filament\Actions\HousekeepingActions;
use App\Filament\Resources\MaintenanceSchedules\Pages\ManageMaintenanceSchedules;
use App\Models\Asset;
use App\Models\MaintenanceSchedule;
use App\Models\Room;
use App\Models\RosterPattern;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Recurring tasks (HK-08): preventive maintenance, grounds work and any job that repeats every
 * N days, weeks or months. A task is created on each due date.
 */
class MaintenanceScheduleResource extends Resource
{
    protected static ?string $model = MaintenanceSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Recurring jobs';

    protected static ?string $modelLabel = 'recurring job';

    protected static ?int $navigationSort = 35;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255)->placeholder('e.g. Backwash pool filter'),
            Select::make('task_type')->label('Type')->options(TaskType::options())->required()->live(),
            Select::make('asset_id')->label('Equipment')->options(fn (): array => Asset::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            Select::make('room_id')->label('Room')->options(fn (): array => Room::query()->orderBy('sort_order')->pluck('name', 'id')->all())->searchable(),
            TextInput::make('location')->label('Or place')->maxLength(255),
            TextInput::make('repeat_every')->label('Repeat every')->integer()->minValue(1)->maxValue(365)->default(1)->required(),
            Select::make('repeat_unit')->label('Days, weeks or months')->options(RepeatUnit::options())->default(RepeatUnit::Week->value)->selectablePlaceholder(false)->required()->live(),
            CheckboxList::make('weekdays')
                ->label('On these days')
                ->options(collect(RosterPattern::WEEKDAYS)->map(fn (string $day): string => substr($day, 0, 3))->all())
                ->columns(7)
                ->bulkToggleable()
                ->helperText('Leave all unticked to repeat on the same day as "Next due".')
                ->visible(fn (Get $get): bool => $get('repeat_unit') === RepeatUnit::Week->value || $get('repeat_unit') === RepeatUnit::Week)
                ->columnSpanFull(),
            DatePicker::make('next_due_on')
                ->label('Next due')
                ->required()
                ->default(today())
                ->helperText('Monthly jobs keep this day of the month (the last day in shorter months).'),
            DatePicker::make('ends_on')->label('Until (optional)')->afterOrEqual('next_due_on'),
            TimePicker::make('due_time')->label('Due by (time, optional)')->seconds(false)->helperText('Leave empty for the end of the day.'),
            Select::make('priority')->options(TaskPriority::options())->placeholder('Normal'),
            Select::make('assigned_to')->label('Give to')
                ->options(fn (Get $get): array => HousekeepingActions::assigneeOptions(filled($get('task_type')) ? TaskType::from($get('task_type')) : null))
                ->searchable(),
            TagsInput::make('checklist')->placeholder('Add an item and press Enter')->columnSpanFull(),
            Textarea::make('notes')->label('Notes for the worker')->rows(2)->columnSpanFull(),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['asset', 'room', 'assignee']))
            ->defaultSort('next_due_on')
            ->columns([
                TextColumn::make('title')->searchable()->description(fn (MaintenanceSchedule $record): string => $record->task_type->label()),
                TextColumn::make('where')->label('Where')->state(fn (MaintenanceSchedule $record): string => $record->asset?->name ?? $record->room?->name ?? $record->location ?? '—'),
                TextColumn::make('repeat')->label('Repeats')->state(fn (MaintenanceSchedule $record): string => $record->repeatLabel()),
                TextColumn::make('next_due_on')->label('Next due')->date('D j M Y'),
                TextColumn::make('ends_on')->label('Until')->date('j M Y')->placeholder('No end'),
                TextColumn::make('assignee.name')->label('Given to')->placeholder('Nobody yet'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMaintenanceSchedules::route('/'),
        ];
    }
}
