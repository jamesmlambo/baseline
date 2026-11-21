<?php

declare(strict_types=1);

namespace App\Filament\Resources\CleaningEstimates;

use App\Enums\TaskType;
use App\Filament\Resources\CleaningEstimates\Pages\ManageCleaningEstimates;
use App\Models\AccommodationType;
use App\Models\CleaningEstimate;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * How long each kind of clean takes per room type (HK-07). Shown on tasks; used for rosters in M9.
 */
class CleaningEstimateResource extends Resource
{
    protected static ?string $model = CleaningEstimate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Cleaning times';

    protected static ?string $modelLabel = 'cleaning time';

    protected static ?int $navigationSort = 41;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('accommodation_type_id')->label('Room type')
                ->options(fn (): array => AccommodationType::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                ->required(),
            Select::make('task_type')->label('Task')
                ->options(collect(TaskType::cases())->filter(fn (TaskType $type): bool => $type->isHousekeeping())->mapWithKeys(fn (TaskType $type): array => [$type->value => $type->label()])->all())
                ->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('accommodation_type_id', $get('accommodation_type_id'))),
            TextInput::make('minutes')->integer()->minValue(1)->maxValue(600)->required()->suffix('min'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('accommodationType'))
            ->columns([
                TextColumn::make('accommodationType.name')->label('Room type')->sortable(),
                TextColumn::make('task_type')->label('Task')->formatStateUsing(fn (TaskType $state): string => $state->label()),
                TextColumn::make('minutes')->suffix(' min'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCleaningEstimates::route('/'),
        ];
    }
}
