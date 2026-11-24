<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices;

use App\Actions\Payments\IssueBookingInvoice;
use App\Filament\Forms\MoneyInput;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Booking;
use App\Models\Invoice;
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The Invoices section (PAY-02): every invoice issued, with search, PAID/UNPAID status,
 * new invoices against bookings, and sending by email or WhatsApp.
 */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Bookings';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Invoice against a booking')
                ->description('The invoice shows UNPAID with the amount to pay, and PAID once the money is received.')
                ->schema([
                    Select::make('booking_id')
                        ->label('Booking')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Booking::query()
                            ->where(fn (Builder $query) => $query->where('reference', 'ilike', "%{$search}%")->orWhere('lead_guest_name', 'ilike', "%{$search}%"))
                            ->latest('id')
                            ->limit(15)
                            ->get()
                            ->mapWithKeys(fn (Booking $booking): array => [$booking->id => self::bookingLabel($booking)])
                            ->all())
                        ->getOptionLabelUsing(fn ($value): ?string => ($booking = Booking::query()->find($value)) ? self::bookingLabel($booking) : null)
                        ->helperText('Search by booking reference or guest name.')
                        ->live(),
                    Radio::make('kind')
                        ->label('What to invoice')
                        ->required()
                        ->default(IssueBookingInvoice::CHARGE)
                        ->options([
                            IssueBookingInvoice::STAY => 'The whole stay (only for a booking without an invoice)',
                            IssueBookingInvoice::CHARGE => 'An extra charge on its own invoice',
                        ])
                        ->live(),
                    TextInput::make('description')
                        ->required(fn (Get $get): bool => $get('kind') === IssueBookingInvoice::CHARGE)
                        ->visible(fn (Get $get): bool => $get('kind') === IssueBookingInvoice::CHARGE)
                        ->maxLength(255),
                    TextInput::make('quantity')
                        ->integer()
                        ->minValue(1)
                        ->default(1)
                        ->required(fn (Get $get): bool => $get('kind') === IssueBookingInvoice::CHARGE)
                        ->visible(fn (Get $get): bool => $get('kind') === IssueBookingInvoice::CHARGE),
                    MoneyInput::make('unit_price')
                        ->label('Unit price')
                        ->required(fn (Get $get): bool => $get('kind') === IssueBookingInvoice::CHARGE)
                        ->visible(fn (Get $get): bool => $get('kind') === IssueBookingInvoice::CHARGE),
                    Toggle::make('send_email')
                        ->label('Email it to the guest now')
                        ->helperText('Uses the email address on the booking.')
                        ->default(false)
                        ->live(),
                    InvoiceActions::messageField()
                        ->visible(fn (Get $get): bool => (bool) $get('send_email')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['booking', 'payments', 'deliveries']))
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('number')->searchable()->sortable()->weight('bold'),
                TextColumn::make('issued_at')->label('Issued')->dateTime('j M Y', 'Africa/Johannesburg')->sortable(),
                TextColumn::make('recipient_name')->label('Customer')->searchable(),
                TextColumn::make('booking.reference')
                    ->label('Booking')
                    ->placeholder('Walk-in')
                    ->searchable()
                    ->url(fn (Invoice $record): ?string => $record->booking ? BookingResource::getUrl('view', ['record' => $record->booking]) : null),
                TextColumn::make('total_cents')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn (Money $state): string => $state->format()),
                TextColumn::make('balance')
                    ->label('To pay')
                    ->alignEnd()
                    ->state(fn (Invoice $record): string => $record->statusLabel() === 'UNPAID' ? Money::cents(max(0, $record->balanceDue()->cents))->format() : '—'),
                self::statusColumn(),
                EmailHistory::column(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['paid' => 'Paid', 'unpaid' => 'Unpaid', 'void' => 'Void'])
                    ->query(fn (Builder $query, array $data): Builder => self::filterByStatus($query, $data['value'] ?? null)),
                Filter::make('walk_in')->label('Walk-in only')->query(fn (Builder $query): Builder => $query->whereNull('booking_id')),
            ])
            ->recordUrl(fn (Invoice $record): string => self::getUrl('view', ['record' => $record]))
            ->recordActions([
                InvoiceActions::open(),
                InvoiceActions::download(),
                InvoiceActions::email(),
                InvoiceActions::whatsApp(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Invoice')
                ->columns(3)
                ->schema([
                    TextEntry::make('number')->weight('bold'),
                    TextEntry::make('status')
                        ->state(fn (Invoice $record): string => $record->statusLabel())
                        ->badge()
                        ->size('lg')
                        ->color(fn (string $state): string => self::statusColour($state)),
                    TextEntry::make('issued_at')->label('Issued')->dateTime('j M Y H:i', 'Africa/Johannesburg'),
                    TextEntry::make('recipient_name')->label('Customer'),
                    TextEntry::make('booking.reference')
                        ->label('Booking')
                        ->placeholder('Walk-in')
                        ->url(fn (Invoice $record): ?string => $record->booking ? BookingResource::getUrl('view', ['record' => $record->booking]) : null),
                    TextEntry::make('due_on')->label('Due')->date('j M Y')->placeholder('—'),
                ]),
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
                ]),
            EmailHistory::section(),
            Section::make('Amounts')
                ->columns(4)
                ->schema([
                    TextEntry::make('total_cents')->label('Total')->formatStateUsing(fn (Money $state): string => $state->format())->weight('bold'),
                    TextEntry::make('paid')->state(fn (Invoice $record): string => $record->amountPaid()->format()),
                    TextEntry::make('credited')->state(fn (Invoice $record): string => $record->creditedAmount()->format()),
                    TextEntry::make('to_pay')
                        ->label('To pay')
                        ->state(fn (Invoice $record): string => $record->statusLabel() === 'UNPAID' ? Money::cents(max(0, $record->balanceDue()->cents))->format() : '—')
                        ->weight('bold'),
                ]),
        ]);
    }

    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->state(fn (Invoice $record): string => $record->statusLabel())
            ->badge()
            ->color(fn (string $state): string => self::statusColour($state));
    }

    public static function statusColour(string $state): string
    {
        return match ($state) {
            'PAID' => 'success',
            'UNPAID' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Paid status depends on the payments ledger, so the filter checks each candidate invoice.
     */
    private static function filterByStatus(Builder $query, ?string $status): Builder
    {
        if ($status === null || $status === '') {
            return $query;
        }

        if ($status === 'void') {
            return $query->whereNotNull('voided_at');
        }

        $wanted = $status === 'paid' ? 'PAID' : 'UNPAID';
        $ids = Invoice::query()->whereNull('voided_at')->with('booking')->get()
            ->filter(fn (Invoice $invoice): bool => $invoice->statusLabel() === $wanted)
            ->modelKeys();

        return $query->whereKey($ids);
    }

    private static function bookingLabel(Booking $booking): string
    {
        return sprintf('%s · %s · %s – %s', $booking->reference, $booking->lead_guest_name, $booking->arrival_date->format('j M'), $booking->departure_date->format('j M Y'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
