<?php

declare(strict_types=1);

namespace App\Filament\Resources\RosterPatterns;

use App\Filament\Resources\RosterPatterns\Pages\ManageRosterPatterns;
use App\Models\Employee;
use App\Models\RosterPattern;
use App\Models\Shift;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The weekly roster (EMP-02). End a pattern with "until" rather than deleting it, so history stays correct.
 */
class RosterPatternResource extends Resource
{
    protected static ?string $model = RosterPattern::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Weekly roster';

    protected static ?string $modelLabel = 'roster line';

    protected static ?int $navigationSort = 25;

    /**
     * Shared fields; the create form picks several weekdays at once.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employee_id')->label('Employee')->options(fn (): array => Employee::query()->active()->orderBy('full_name')->pluck('full_name', 'id')->all())->searchable()->required(),
            Select::make('shift_id')->label('Shift')->options(fn (): array => Shift::query()->where('is_active', true)->orderBy('starts_at')->get()->mapWithKeys(fn (Shift $shift): array => [$shift->id => "{$shift->name} ({$shift->timesLabel()})"])->all())->required(),
            Select::make('weekday')->options(RosterPattern::WEEKDAYS)->required()->visibleOn('edit'),
            CheckboxList::make('weekdays')->options(RosterPattern::WEEKDAYS)->columns(4)->required()->visibleOn('create'),
            DatePicker::make('effective_from')->label('From')->required()->default(today()),
            DatePicker::make('effective_until')->label('Until (optional)')->afterOrEqual('effective_from'),
            Toggle::make('works_public_holidays')->label('Works on public holidays')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['employee', 'shift']))
            ->defaultGroup('employee.full_name')
            ->defaultSort('weekday')
            ->columns([
                TextColumn::make('weekday')->formatStateUsing(fn (int $state): string => RosterPattern::WEEKDAYS[$state]),
                TextColumn::make('shift.name')->label('Shift')->description(fn (RosterPattern $record): string => $record->shift->timesLabel()),
                TextColumn::make('effective_from')->label('From')->date('j M Y'),
                TextColumn::make('effective_until')->label('Until')->date('j M Y')->placeholder('Ongoing'),
                IconColumn::make('works_public_holidays')->label('Public holidays')->boolean(),
            ])
            ->filters([
                SelectFilter::make('employee_id')->label('Employee')->relationship('employee', 'full_name'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRosterPatterns::route('/')];
    }
}
