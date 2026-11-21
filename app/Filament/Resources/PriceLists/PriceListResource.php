<?php

declare(strict_types=1);

namespace App\Filament\Resources\PriceLists;

use App\Actions\Messaging\CreatePriceList;
use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\PriceLists\Pages\CreatePriceListPage;
use App\Filament\Resources\PriceLists\Pages\ListPriceLists;
use App\Models\PriceList;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * WhatsApp price lists (WA-01): secure, expiring links with live availability for a guest's dates.
 */
class PriceListResource extends Resource
{
    protected static ?string $model = PriceList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Bookings';

    protected static ?string $navigationLabel = 'WhatsApp price lists';

    protected static ?string $modelLabel = 'price list';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dates and guests')
                ->columns(2)
                ->schema([
                    DatePicker::make('arrival')->required()->native(false)->minDate(today()),
                    DatePicker::make('departure')->required()->native(false)->after('arrival'),
                    TextInput::make('adults')->integer()->minValue(1)->maxValue(12)->default(2)->required(),
                    TextInput::make('children')->integer()->minValue(0)->maxValue(12)->default(0),
                ]),
            Section::make('Customer (optional)')
                ->columns(2)
                ->description('Add the WhatsApp number to send the link automatically. You can also send it yourself from WhatsApp afterwards.')
                ->schema([
                    TextInput::make('customer_name')->label('First name')->maxLength(80),
                    TextInput::make('customer_phone')->label('WhatsApp number')->tel()->maxLength(32),
                    Toggle::make('send')->label('Send the link now')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['booking', 'creator']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Created')->dateTime('j M H:i')->sortable(),
                TextColumn::make('customer_name')->label('Customer')->placeholder('—'),
                TextColumn::make('stay')->label('Dates')
                    ->state(fn (PriceList $record): string => $record->arrival_date->format('j M').' – '.$record->departure_date->format('j M Y')),
                TextColumn::make('guests')->state(fn (PriceList $record): string => $record->adults.($record->children ? ' + '.$record->children : '')),
                TextColumn::make('open_count')->label('Opened')->formatStateUsing(fn (int $state): string => $state === 0 ? 'Not yet' : $state.'×'),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (PriceList $record): string => match (true) {
                        $record->booking_id !== null => 'Booked',
                        $record->revoked_at !== null => 'Cancelled',
                        ! $record->isUsable() => 'Expired',
                        default => 'Active',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Booked' => 'success',
                        'Active' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('booking.reference')
                    ->label('Booking')
                    ->placeholder('—')
                    ->url(fn (PriceList $record): ?string => $record->booking ? BookingResource::getUrl('view', ['record' => $record->booking]) : null),
                TextColumn::make('creator.name')->label('By')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->url(fn (PriceList $record): string => $record->url(), shouldOpenInNewTab: true)
                    ->visible(fn (PriceList $record): bool => $record->isUsable()),
                Action::make('whatsapp')
                    ->label('Send from WhatsApp')
                    ->icon('heroicon-o-chat-bubble-oval-left')
                    ->color('success')
                    ->url(fn (PriceList $record): ?string => CreatePriceList::clickToChatUrl($record), shouldOpenInNewTab: true)
                    ->visible(fn (PriceList $record): bool => $record->isUsable()),
                Action::make('revoke')
                    ->label('Cancel link')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->authorize(fn (PriceList $record): bool => auth()->user()->can('revoke', $record))
                    ->visible(fn (PriceList $record): bool => $record->isUsable())
                    ->requiresConfirmation()
                    ->action(fn (PriceList $record) => PaymentActions::run(fn () => $record->forceFill(['revoked_at' => now()])->save(), 'Link cancelled')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceLists::route('/'),
            'create' => CreatePriceListPage::route('/create'),
        ];
    }
}
