<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccommodationTypes\Schemas;

use App\Actions\Inventory\SetRoomCount;
use App\Filament\Forms\MoneyInput;
use App\Models\Amenity;
use App\Support\Money;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AccommodationTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Room type')
                    ->description('Guests book a room type. A specific room is assigned automatically at booking.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation): void {
                                if ($operation === 'create' || blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->helperText('Used in the website address.'),
                        TextInput::make('room_count')
                            ->label('Number of rooms')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(SetRoomCount::MAX_ROOMS)
                            ->required()
                            ->default(1)
                            ->helperText('Raise it when a room becomes available: new rooms are added and can be renamed in the Rooms list. Lowering it switches off rooms without upcoming bookings; nothing is deleted.'),
                        Textarea::make('summary')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),
                Section::make('Photos and amenities')
                    ->description('Shown on the public website.')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->label('Photos')
                            ->collection('gallery')
                            ->disk(config('media-library.disk_name'))
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->imageEditor()
                            ->maxSize(8192)
                            ->maxFiles(20)
                            ->helperText('The first photo is the main one. JPEG, PNG or WebP up to 8 MB. Location data is removed automatically.'),
                        CheckboxList::make('amenities')
                            ->relationship('amenities', 'name', fn ($query) => $query->where('category', 'room')->orderBy('sort_order'))
                            ->getOptionLabelFromRecordUsing(fn (Amenity $record): string => $record->labelWithIcon())
                            ->allowHtml()
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(3)
                            ->helperText('Manage the list under Website › Amenities.'),
                    ]),
                Section::make('Occupancy and pricing')
                    ->columns(2)
                    ->schema([
                        TextInput::make('base_occupancy')
                            ->label('Guests included in the rate')
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->default(2),
                        TextInput::make('max_occupancy')
                            ->label('Maximum guests')
                            ->integer()
                            ->minValue(1)
                            ->gte('base_occupancy')
                            ->required()
                            ->default(2),
                        MoneyInput::make('base_nightly_rate_cents')
                            ->label('Base nightly rate')
                            ->required()
                            ->helperText('Used on nights without a seasonal rate.'),
                        MoneyInput::make('extra_guest_nightly_cents')
                            ->label('Extra guest per night')
                            ->default('0.00')
                            ->required(),
                    ]),
                Section::make('Visibility')
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Bookable')
                            ->helperText('Set a base nightly rate above R0.00 first.')
                            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if ($value && self::isZeroRate($get('base_nightly_rate_cents'))) {
                                    $fail('A room type cannot be bookable at R0.00. Set the base nightly rate first.');
                                }
                            })
                            ->default(true),
                        Toggle::make('is_demo')
                            ->label('Demo data')
                            ->helperText('Must be off for real rooms before launch.')
                            ->default(false),
                        TextInput::make('sort_order')
                            ->integer()
                            ->default(0),
                    ]),
            ]);
    }

    /**
     * True when the typed base rate is zero. Blank or unreadable amounts are left to the rate field's own validation.
     */
    private static function isZeroRate(mixed $state): bool
    {
        if ($state instanceof Money) {
            return $state->isZero();
        }

        try {
            return filled($state) && Money::fromRands(str_replace(',', '', (string) $state))->isZero();
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
