<?php

declare(strict_types=1);

namespace App\Filament\Resources\Services;

use App\Enums\ServiceCategory;
use App\Enums\ServiceResourceType;
use App\Filament\Forms\MoneyInput;
use App\Filament\Resources\Services\Pages\ManageServices;
use App\Models\RosterPattern;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Bookable services (LSR-01/02): hours, slots, capacity, rules and price.
 */
class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Leisure';

    protected static ?string $navigationLabel = 'Services';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Service')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(255)
                    ->afterStateUpdated(fn ($state, callable $set, ?Service $record) => $record === null ? $set('slug', Str::slug((string) $state)) : null)
                    ->live(onBlur: true),
                TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),
                Select::make('category')->options(ServiceCategory::options())->required(),
                MoneyInput::make('price_cents')->label('Price')->required(),
                Select::make('pricing')->options([Service::PER_PERSON => 'Per person', Service::PER_BOOKING => 'Per booking'])->default(Service::PER_PERSON)->required(),
                Textarea::make('description')->rows(2)->columnSpanFull(),
                Textarea::make('restrictions')->label('Restrictions shown to guests')->rows(2)->placeholder('e.g. Children must be supervised')->columnSpanFull(),
            ]),
            Section::make('Slots and capacity')->columns(3)->schema([
                TextInput::make('duration_minutes')->label('Duration (minutes)')->integer()->minValue(5)->maxValue(1440)->required(),
                TextInput::make('slot_interval_minutes')->label('A new slot every (minutes)')->integer()->minValue(5)->maxValue(1440)->default(60)->required(),
                TextInput::make('min_notice_minutes')->label('Book at least (minutes) ahead')->integer()->minValue(0)->default(0)->required(),
                TextInput::make('capacity')->label('Capacity at one time')->integer()->minValue(1)->helperText('Leave empty if only therapists/rooms limit it.'),
                Select::make('capacity_unit')->label('Capacity counts')->options([Service::CAPACITY_PEOPLE => 'People', Service::CAPACITY_BOOKINGS => 'Bookings (e.g. one group per VIP area)'])->default(Service::CAPACITY_PEOPLE)->required(),
                TextInput::make('max_party_size')->label('Largest group')->integer()->minValue(1)->default(10)->required(),
                TextInput::make('cancellation_cutoff_hours')->label('Guests may cancel until (hours before)')->integer()->minValue(0)->default(24)->required(),
                CheckboxList::make('resource_types')->label('Needs one free…')->options(ServiceResourceType::options())->helperText('Spa: therapist + treatment room.')->columnSpan(2),
            ]),
            Section::make('Opening hours')->description('Leave a day empty when closed.')->schema(
                collect(RosterPattern::WEEKDAYS)->map(fn (string $day, int $number): Grid => Grid::make(3)->schema([
                    TextInput::make("day_{$number}_label")->hiddenLabel()->default($day)->disabled()->dehydrated(false)->formatStateUsing(fn (): string => $day),
                    TimePicker::make("opening_hours.{$number}.0")->label('Opens')->seconds(false),
                    TimePicker::make("opening_hours.{$number}.1")->label('Closes')->seconds(false)->after("opening_hours.{$number}.0"),
                ]))->values()->all(),
            ),
            Section::make('Who can book')->columns(2)->schema([
                Toggle::make('bookable_by_guests')->label('In-house guests can book in the guest portal')->default(true),
                Toggle::make('charge_to_room_allowed')->label('Can be charged to the room')->default(true),
                Toggle::make('requires_indemnity')->label('Needs a safety acknowledgement (when that feature is on)'),
                Toggle::make('is_active')->label('Bookable')->default(true),
                Toggle::make('is_demo')->label('Demo service'),
                TextInput::make('sort_order')->integer()->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (Service $record): string => $record->category->label()),
                TextColumn::make('price_cents')->label('Price')->formatStateUsing(fn (Service $record): string => $record->priceLabel()),
                TextColumn::make('duration_minutes')->label('Duration')->suffix(' min'),
                TextColumn::make('capacity')->label('Capacity')->placeholder('By resources')
                    ->formatStateUsing(fn (Service $record): string => $record->capacity.' '.($record->capacity_unit === Service::CAPACITY_BOOKINGS ? 'bookings' : 'people')),
                IconColumn::make('bookable_by_guests')->label('Portal')->boolean(),
                IconColumn::make('is_active')->label('Bookable')->boolean(),
                IconColumn::make('is_demo')->label('Demo')->boolean()->trueColor('warning')->falseColor('success'),
            ])
            ->recordActions([EditAction::make()->mutateDataUsing(fn (array $data): array => static::cleanHours($data))]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageServices::route('/')];
    }

    /**
     * Drop empty days so a half-filled day doesn't count as open.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function cleanHours(array $data): array
    {
        $data['opening_hours'] = collect($data['opening_hours'] ?? [])
            ->filter(fn (mixed $hours): bool => is_array($hours) && filled($hours[0] ?? null) && filled($hours[1] ?? null))
            ->map(fn (array $hours): array => [substr((string) $hours[0], 0, 5), substr((string) $hours[1], 0, 5)])
            ->all();

        return $data;
    }
}
