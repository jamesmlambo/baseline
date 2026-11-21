<?php

declare(strict_types=1);

namespace App\Filament\Resources\RosterChanges;

use App\Filament\Resources\RosterChanges\Pages\ManageRosterChanges;
use App\Models\Employee;
use App\Models\RosterChange;
use App\Models\Shift;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * One-day exceptions and cover (EMP-02).
 */
class RosterChangeResource extends Resource
{
    protected static ?string $model = RosterChange::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Roster changes & cover';

    protected static ?string $modelLabel = 'roster change';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employee_id')->label('Employee')->options(fn (): array => Employee::query()->active()->orderBy('full_name')->pluck('full_name', 'id')->all())->searchable()->required(),
            DatePicker::make('work_date')->label('Date')->required(),
            Select::make('shift_id')->label('Shift')
                ->options(fn (): array => Shift::query()->where('is_active', true)->orderBy('starts_at')->get()->mapWithKeys(fn (Shift $shift): array => [$shift->id => "{$shift->name} ({$shift->timesLabel()})"])->all())
                ->placeholder('Day off')
                ->helperText('Leave empty to give a day off.'),
            Select::make('covering_for_employee_id')->label('Covering for')->options(fn (): array => Employee::query()->active()->orderBy('full_name')->pluck('full_name', 'id')->all())->searchable(),
            TextInput::make('reason')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['employee', 'shift', 'coveringFor']))
            ->defaultSort('work_date', 'desc')
            ->columns([
                TextColumn::make('work_date')->label('Date')->date('D j M Y')->sortable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                TextColumn::make('shift.name')->label('Shift')->placeholder('Day off'),
                TextColumn::make('coveringFor.full_name')->label('Covering for')->placeholder('—'),
                TextColumn::make('reason')->wrap(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRosterChanges::route('/')];
    }
}
