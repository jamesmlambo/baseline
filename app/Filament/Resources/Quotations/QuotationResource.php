<?php

declare(strict_types=1);

namespace App\Filament\Resources\Quotations;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Invoices\EmailHistory;
use App\Filament\Resources\Invoices\InvoiceActions;
use App\Filament\Resources\Quotations\Pages\CreateQuotation;
use App\Filament\Resources\Quotations\Pages\ListQuotations;
use App\Filament\Resources\Quotations\Pages\ViewQuotation;
use App\Models\AccommodationType;
use App\Models\Extra;
use App\Models\Quotation;
use App\Models\Service;
use App\Services\Finance\QuoteBuilder;
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Throwable;
use UnitEnum;

/**
 * The Quotations section (PAY-01): quotes priced from room types (rate engine), extras and
 * leisure services such as spa and day passes; viewed, downloaded, emailed or sent by WhatsApp.
 */
class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Bookings';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Customer')
                ->columns(3)
                ->schema([
                    TextInput::make('recipient_name')->label('Name')->required()->maxLength(255),
                    TextInput::make('recipient_email')->label('Email')->email()->maxLength(255),
                    TextInput::make('recipient_phone')->label('Phone / WhatsApp')->tel()->maxLength(32),
                ]),
            Section::make('Stay')
                ->description('Needed to quote rooms. Extras priced per night use these dates too.')
                ->columns(2)
                ->schema([
                    DatePicker::make('arrival')->native(false)->minDate(today())->live(),
                    DatePicker::make('departure')->native(false)->after('arrival')->live(),
                    Repeater::make('rooms')
                        ->label('Rooms')
                        ->addActionLabel('Add a room type')
                        ->defaultItems(0)
                        ->columns(4)
                        ->columnSpanFull()
                        ->live()
                        ->schema([
                            Select::make('accommodation_type_id')
                                ->label('Room type')
                                ->options(fn (): array => self::roomTypeOptions())
                                ->required()
                                ->columnSpan(2),
                            TextInput::make('rooms')->label('Rooms')->integer()->minValue(1)->default(1)->required(),
                            TextInput::make('adults')->label('Adults per room')->integer()->minValue(1)->default(2)->required(),
                            TextInput::make('children')->label('Children per room')->integer()->minValue(0)->default(0),
                        ]),
                ]),
            Section::make('Extras')
                ->schema([
                    Repeater::make('extras')
                        ->hiddenLabel()
                        ->addActionLabel('Add an extra')
                        ->defaultItems(0)
                        ->columns(3)
                        ->live()
                        ->schema([
                            Select::make('extra_id')
                                ->label('Extra')
                                ->options(fn (): array => Extra::query()->where('is_active', true)->orderBy('sort_order')->get()
                                    ->mapWithKeys(fn (Extra $extra): array => [$extra->id => "{$extra->name} · {$extra->price_cents->format()} {$extra->pricing_unit->label()}"])
                                    ->all())
                                ->required()
                                ->columnSpan(2),
                            TextInput::make('quantity')
                                ->integer()
                                ->minValue(1)
                                ->helperText('Leave empty to work it out from the nights and guests.'),
                        ]),
                ]),
            Section::make('Spa, day passes and other services')
                ->schema([
                    Repeater::make('services')
                        ->hiddenLabel()
                        ->addActionLabel('Add a service')
                        ->defaultItems(0)
                        ->columns(4)
                        ->live()
                        ->schema([
                            Select::make('service_id')
                                ->label('Service')
                                ->options(fn (): array => Service::query()->where('is_active', true)->orderBy('category')->orderBy('sort_order')->get()
                                    ->mapWithKeys(fn (Service $service): array => [$service->id => sprintf(
                                        '%s (%s) · %s %s',
                                        $service->name,
                                        $service->category->label(),
                                        $service->price_cents->format(),
                                        $service->pricing === Service::PER_PERSON ? 'per person' : 'per booking',
                                    )])
                                    ->all())
                                ->required()
                                ->columnSpan(2),
                            TextInput::make('people')->integer()->minValue(1)->default(1)->required(),
                            TextInput::make('sessions')->integer()->minValue(1)->default(1)->required(),
                        ]),
                ]),
            Section::make('Quotation')
                ->columns(2)
                ->schema([
                    Text::make(fn (Get $get): string => self::previewTotal($get))
                        ->columnSpanFull(),
                    TextInput::make('valid_days')
                        ->label('Valid for (days)')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(90)
                        ->default(7)
                        ->required(),
                    Textarea::make('notes')
                        ->label('Note printed on the quotation (optional)')
                        ->rows(3)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    Toggle::make('send_email')
                        ->label('Email it to the customer now')
                        ->default(false)
                        ->live(),
                    InvoiceActions::messageField()
                        ->visible(fn (Get $get): bool => (bool) $get('send_email'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['booking', 'deliveries']))
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('number')->searchable()->sortable()->weight('bold'),
                TextColumn::make('issued_at')->label('Issued')->dateTime('j M Y', 'Africa/Johannesburg')->sortable(),
                TextColumn::make('recipient_name')->label('Customer')->searchable(),
                TextColumn::make('total_cents')->label('Total')->alignEnd()->formatStateUsing(fn (Money $state): string => $state->format()),
                TextColumn::make('valid_until')->label('Valid until')->date('j M Y')->placeholder('—'),
                TextColumn::make('status')
                    ->state(fn (Quotation $record): string => $record->statusLabel())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Valid' => 'success',
                        'Booked', 'Converted' => 'info',
                        default => 'gray',
                    }),
                EmailHistory::column(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['valid' => 'Valid', 'expired' => 'Expired'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'valid' => $query->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', today())),
                        'expired' => $query->whereDate('valid_until', '<', today()),
                        default => $query,
                    }),
            ])
            ->recordUrl(fn (Quotation $record): string => self::getUrl('view', ['record' => $record]))
            ->recordActions([
                QuotationActions::book(),
                QuotationActions::open(),
                QuotationActions::download(),
                QuotationActions::email(),
                QuotationActions::whatsApp(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Quotation')
                ->columns(3)
                ->schema([
                    TextEntry::make('number')->weight('bold'),
                    TextEntry::make('status')
                        ->state(fn (Quotation $record): string => $record->statusLabel())
                        ->badge()
                        ->color(fn (string $state): string => $state === 'Valid' ? 'success' : 'gray'),
                    TextEntry::make('issued_at')->label('Issued')->dateTime('j M Y H:i', 'Africa/Johannesburg'),
                    TextEntry::make('recipient_name')->label('Customer'),
                    TextEntry::make('recipient_email')->label('Email')->placeholder('—'),
                    TextEntry::make('recipient_phone')->label('Phone')->placeholder('—'),
                    TextEntry::make('valid_until')->label('Valid until')->date('j M Y')->placeholder('—'),
                    TextEntry::make('booking.reference')
                        ->label('Booking')
                        ->placeholder('—')
                        ->url(fn (Quotation $record): ?string => $record->booking ? BookingResource::getUrl('view', ['record' => $record->booking]) : null),
                    TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                ]),
            EmailHistory::section(),
            Section::make('Lines')
                ->schema([
                    RepeatableEntry::make('lines')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('description')->columnSpan(2),
                            TextEntry::make('quantity')->formatStateUsing(fn ($state): string => rtrim(rtrim((string) $state, '0'), '.')),
                            TextEntry::make('total_cents')->label('Amount')->formatStateUsing(fn (Money $state): string => $state->format()),
                        ]),
                    TextEntry::make('total_cents')->label('Total')->formatStateUsing(fn (Money $state): string => $state->format())->weight('bold'),
                ]),
        ]);
    }

    /**
     * Prices the form's selections; used for the live total and when the quotation is created.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{description: string, quantity: int, unit: Money, total: Money}>
     */
    public static function buildLines(array $data): array
    {
        return app(QuoteBuilder::class)->linesForItems(self::items($data));
    }

    /**
     * The form's selections in the shape stored on the quotation (`quotations.items`).
     *
     * @param  array<string, mixed>  $data
     * @return array{arrival: string|null, departure: string|null, rooms: list<array<string, int|null>>, extras: list<array<string, int|null>>, services: list<array<string, int|null>>}
     */
    public static function items(array $data): array
    {
        $int = fn (mixed $value): ?int => filled($value) ? (int) $value : null;

        return [
            'arrival' => filled($data['arrival'] ?? null) ? (string) $data['arrival'] : null,
            'departure' => filled($data['departure'] ?? null) ? (string) $data['departure'] : null,
            'rooms' => array_values(array_map(fn (array $row): array => [
                'accommodation_type_id' => (int) $row['accommodation_type_id'],
                'rooms' => max(1, (int) ($row['rooms'] ?? 1)),
                'adults' => (int) ($row['adults'] ?? 0),
                'children' => (int) ($row['children'] ?? 0),
            ], array_filter($data['rooms'] ?? [], fn (array $row): bool => filled($row['accommodation_type_id'] ?? null)))),
            'extras' => array_values(array_map(fn (array $row): array => [
                'extra_id' => (int) $row['extra_id'],
                'quantity' => $int($row['quantity'] ?? null),
            ], array_filter($data['extras'] ?? [], fn (array $row): bool => filled($row['extra_id'] ?? null)))),
            'services' => array_values(array_map(fn (array $row): array => [
                'service_id' => (int) $row['service_id'],
                'people' => max(1, (int) ($row['people'] ?? 1)),
                'sessions' => max(1, (int) ($row['sessions'] ?? 1)),
            ], array_filter($data['services'] ?? [], fn (array $row): bool => filled($row['service_id'] ?? null)))),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function roomTypeOptions(): array
    {
        return AccommodationType::query()
            ->where('base_nightly_rate_cents', '>', 0)
            ->orderBy('is_demo')
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (AccommodationType $type): array => [
                $type->id => $type->name.' · from '.$type->base_nightly_rate_cents->format().' a night'.($type->is_demo ? ' (DEMO)' : ''),
            ])
            ->all();
    }

    private static function previewTotal(Get $get): string
    {
        try {
            $lines = self::buildLines([
                'arrival' => $get('arrival'),
                'departure' => $get('departure'),
                'rooms' => $get('rooms') ?? [],
                'extras' => $get('extras') ?? [],
                'services' => $get('services') ?? [],
            ]);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        } catch (Throwable) {
            return 'Complete the items above to see the total.';
        }

        $total = array_reduce($lines, fn (Money $carry, array $line): Money => $carry->add($line['total']), Money::zero());

        return sprintf('Total before VAT: %s (%d %s). Room types without a price are not listed.', $total->format(), count($lines), count($lines) === 1 ? 'item' : 'items');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuotations::route('/'),
            'create' => CreateQuotation::route('/create'),
            'view' => ViewQuotation::route('/{record}'),
        ];
    }
}
