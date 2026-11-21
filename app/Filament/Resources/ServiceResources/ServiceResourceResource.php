<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceResources;

use App\Enums\ServiceResourceType;
use App\Filament\Resources\ServiceResources\Pages\ManageServiceResources;
use App\Models\Employee;
use App\Models\ServiceResource;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Therapists, treatment rooms and bookable areas (LSR-03).
 */
class ServiceResourceResource extends Resource
{
    protected static ?string $model = ServiceResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Leisure';

    protected static ?string $navigationLabel = 'Therapists & rooms';

    protected static ?string $modelLabel = 'therapist or room';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            Select::make('type')->options(ServiceResourceType::options())->required(),
            Select::make('employee_id')->label('Employee (therapists)')->options(fn (): array => Employee::query()->active()->orderBy('full_name')->pluck('full_name', 'id')->all())->searchable(),
            Toggle::make('is_active')->label('Available')->default(true),
            Toggle::make('is_demo')->label('Demo'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('type')->formatStateUsing(fn (ServiceResourceType $state): string => $state->label()),
                IconColumn::make('is_active')->label('Available')->boolean(),
                IconColumn::make('is_demo')->label('Demo')->boolean()->trueColor('warning')->falseColor('success'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageServiceResources::route('/')];
    }
}
