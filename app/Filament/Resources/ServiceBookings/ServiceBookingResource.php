<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceBookings;

use App\Enums\PaymentMethod;
use App\Enums\ServiceBookingStatus;
use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\ServiceBookings\Pages\ListServiceBookings;
use App\Models\ServiceBooking;
use App\Services\Leisure\ServiceBookings;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Leisure bookings for reception (LSR-01..05).
 */
class ServiceBookingResource extends Resource
{
    protected static ?string $model = ServiceBooking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Leisure';

    protected static ?string $navigationLabel = 'Leisure bookings';

    protected static ?string $modelLabel = 'leisure booking';

    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        $today = ServiceBooking::query()->active()->whereDate('starts_at', today())->count();

        return $today > 0 ? (string) $today : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Booked for today';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('60s')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['service', 'stay']))
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('starts_at')->label('When')->dateTime('D j M H:i', 'Africa/Johannesburg')->sortable()
                    ->description(fn (ServiceBooking $record): string => 'until '.$record->ends_at->setTimezone('Africa/Johannesburg')->format('H:i')),
                TextColumn::make('service.name')->label('Service')->description(fn (ServiceBooking $record): string => $record->reference),
                TextColumn::make('customer_name')->label('For')->searchable()
                    ->description(fn (ServiceBooking $record): string => $record->party_size.' '.($record->party_size === 1 ? 'person' : 'people')),
                TextColumn::make('stay.reference')->label('Stay')->placeholder('Walk-in')
                    ->url(fn (ServiceBooking $record): ?string => $record->stay ? BookingResource::getUrl('view', ['record' => $record->stay]) : null),
                TextColumn::make('total_cents')->label('Price')->formatStateUsing(fn (Money $state): string => $state->format())
                    ->description(fn (ServiceBooking $record): string => match (true) {
                        $record->charge_to_room => $record->invoice_id ? 'On room bill' : 'To room bill when done',
                        $record->invoice_id !== null => 'Paid',
                        $record->total_cents->isZero() => 'Free',
                        default => 'Not paid',
                    }),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ServiceBookingStatus $state): string => $state->label())
                    ->color(fn (ServiceBookingStatus $state): string => $state->color()),
            ])
            ->filters([
                Filter::make('from')
                    ->schema([DatePicker::make('from')->label('From')->default(today())])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['from'] ?? null, fn (Builder $inner, string $from) => $inner->whereDate('starts_at', '>=', $from))),
                SelectFilter::make('status')->multiple()->options(ServiceBookingStatus::options())->default([ServiceBookingStatus::Confirmed->value]),
                SelectFilter::make('service_id')->label('Service')->relationship('service', 'name'),
            ])
            ->recordActions([
                Action::make('takePayment')
                    ->label('Take payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (ServiceBooking $record): bool => ! $record->charge_to_room && $record->invoice_id === null && ! $record->total_cents->isZero() && $record->status !== ServiceBookingStatus::Cancelled)
                    ->authorize(fn (ServiceBooking $record): bool => auth()->user()->can('update', $record))
                    ->modalDescription(fn (ServiceBooking $record): string => 'Amount: '.$record->total_cents->format().'. An invoice and (for cash) a receipt are issued.')
                    ->schema([
                        Select::make('method')->options([PaymentMethod::Cash->value => PaymentMethod::Cash->label(), PaymentMethod::Eft->value => PaymentMethod::Eft->label()])->required(),
                        TextInput::make('bank_reference')->label('EFT reference')->maxLength(255),
                    ])
                    ->action(fn (ServiceBooking $record, array $data) => PaymentActions::run(
                        fn () => app(ServiceBookings::class)->takePayment($record, PaymentMethod::from($data['method']), auth()->user(), $data['bank_reference'] ?? null),
                        'Payment recorded',
                    )),
                Action::make('complete')
                    ->label('Done')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ServiceBooking $record): bool => $record->status === ServiceBookingStatus::Confirmed)
                    ->authorize(fn (ServiceBooking $record): bool => auth()->user()->can('update', $record))
                    ->requiresConfirmation()
                    ->modalDescription(fn (ServiceBooking $record): ?string => $record->charge_to_room ? "{$record->total_cents->format()} will be added to the room bill." : null)
                    ->action(fn (ServiceBooking $record) => PaymentActions::run(fn () => app(ServiceBookings::class)->complete($record, auth()->user()), 'Marked as done')),
                ActionGroup::make([
                    Action::make('noShow')
                        ->label('No-show')
                        ->icon('heroicon-o-user-minus')
                        ->visible(fn (ServiceBooking $record): bool => $record->status === ServiceBookingStatus::Confirmed && $record->starts_at->isPast())
                        ->authorize(fn (ServiceBooking $record): bool => auth()->user()->can('update', $record))
                        ->requiresConfirmation()
                        ->action(fn (ServiceBooking $record) => PaymentActions::run(fn () => app(ServiceBookings::class)->noShow($record, auth()->user()), 'Marked as no-show')),
                    Action::make('cancel')
                        ->label('Cancel')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->visible(fn (ServiceBooking $record): bool => $record->status === ServiceBookingStatus::Confirmed)
                        ->authorize(fn (ServiceBooking $record): bool => auth()->user()->can('update', $record))
                        ->schema([Textarea::make('reason')->required()->rows(2)])
                        ->action(fn (ServiceBooking $record, array $data) => PaymentActions::run(fn () => app(ServiceBookings::class)->cancel($record, $data['reason'], auth()->user()), 'Booking cancelled')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListServiceBookings::route('/')];
    }
}
