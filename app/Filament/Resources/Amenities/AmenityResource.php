<?php

declare(strict_types=1);

namespace App\Filament\Resources\Amenities;

use App\Enums\AmenityCategory;
use App\Filament\Resources\Amenities\Pages\CreateAmenity;
use App\Filament\Resources\Amenities\Pages\EditAmenity;
use App\Filament\Resources\Amenities\Pages\ListAmenities;
use App\Models\Amenity;
use BackedEnum;
use Database\Seeders\AmenitySeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AmenityResource extends Resource
{
    protected static ?string $model = Amenity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Amenities';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            Select::make('category')->options(AmenityCategory::options())->required(),
            Select::make('icon')
                ->options(fn (?Amenity $record): array => self::iconOptions($record?->icon))
                ->allowHtml()
                ->searchable()
                ->placeholder('Check mark (default)')
                ->helperText('Shown before the name on the website and in the room tick boxes.'),
            Textarea::make('description')->rows(2)->columnSpanFull(),
            TextInput::make('sort_order')->integer()->default(0),
            Toggle::make('is_published')->label('Published')->default(true),
            Toggle::make('is_demo')->label('Demo content'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->formatStateUsing(fn (Amenity $record): string => $record->labelWithIcon())
                    ->html(),
                TextColumn::make('category')->badge()->formatStateUsing(fn (AmenityCategory $state): string => $state->label()),
                TextColumn::make('accommodation_types_count')->label('Room types')->counts('accommodationTypes'),
                IconColumn::make('is_published')->label('Published')->boolean(),
                IconColumn::make('is_demo')->label('Demo')->boolean()->trueColor('warning')->falseColor('success'),
            ])
            ->filters([SelectFilter::make('category')->options(AmenityCategory::options())])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    /**
     * The amenity icons in use (from AmenitySeeder) plus the record's current one, each with a preview.
     *
     * @return array<string, string>
     */
    private static function iconOptions(?string $current): array
    {
        $icons = collect(AmenitySeeder::AMENITIES)
            ->mapWithKeys(fn (array $row): array => [$row[1] => $row[0]])
            ->when(filled($current) && ! collect(AmenitySeeder::AMENITIES)->contains(fn (array $row): bool => $row[1] === $current), fn ($collection) => $collection->put($current, $current));

        return $icons->map(fn (string $example, string $icon): string => '<span style="display:inline-flex;align-items:center;gap:.4rem">'
            .Amenity::svgFor($icon, 'size-5')->toHtml()
            .'<span>'.e(str($icon)->after('phosphor-')->before('-duotone')->replace('-', ' ')->ucfirst()).'</span></span>')
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAmenities::route('/'),
            'create' => CreateAmenity::route('/create'),
            'edit' => EditAmenity::route('/{record}/edit'),
        ];
    }
}
