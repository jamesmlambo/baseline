<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shifts;

use App\Filament\Resources\Shifts\Pages\ManageShifts;
use App\Models\Department;
use App\Models\Shift;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Shifts and their clock-in/out windows (ATT-03).
 */
class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255)->placeholder('e.g. Morning reception'),
            Select::make('department_id')->label('Department')->options(fn (): array => Department::query()->orderBy('name')->pluck('name', 'id')->all()),
            TimePicker::make('starts_at')->label('Starts')->seconds(false)->required(),
            TimePicker::make('ends_at')->label('Ends')->seconds(false)->required()->helperText('If it ends before it starts, the shift runs past midnight.'),
            TextInput::make('clock_in_opens_minutes')->label('Clock-in opens (minutes before start)')->integer()->minValue(0)->maxValue(600)->default(60)->required(),
            TextInput::make('late_after_minutes')->label('Late after (minutes past start)')->integer()->minValue(0)->maxValue(240)->default(0)->required(),
            TextInput::make('clock_out_closes_minutes')->label('Clock-out closes (minutes after end)')->integer()->minValue(0)->maxValue(720)->default(180)->required(),
            Toggle::make('is_active')->label('In use')->default(true),
            Toggle::make('is_demo')->label('Demo shift'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('department'))
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('name')->description(fn (Shift $record): ?string => $record->department?->name),
                TextColumn::make('times')->label('Times')->state(fn (Shift $record): string => $record->timesLabel()),
                TextColumn::make('late_after_minutes')->label('Late after')->suffix(' min'),
                IconColumn::make('is_active')->label('In use')->boolean(),
                IconColumn::make('is_demo')->label('Demo')->boolean()->trueColor('warning')->falseColor('success'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageShifts::route('/')];
    }
}
