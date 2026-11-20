<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bookings\Tables;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('activeBookingRooms.room'))
            ->defaultSort('arrival_date')
            ->columns([
                TextColumn::make('reference')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('lead_guest_name')
                    ->label('Guest')
                    ->searchable(),
                TextColumn::make('arrival_date')
                    ->label('Arrive')
                    ->date('D j M Y')
                    ->sortable(),
                TextColumn::make('departure_date')
                    ->label('Depart')
                    ->date('D j M Y')
                    ->sortable(),
                TextColumn::make('rooms')
                    ->label('Room')
                    ->state(fn (Booking $record): string => $record->activeBookingRooms->map(fn ($room) => $room->room->name)->implode(', ') ?: '—'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (BookingStatus $state): string => $state->label())
                    ->color(fn (BookingStatus $state): string => match ($state) {
                        BookingStatus::Hold, BookingStatus::Pending, BookingStatus::Quote => 'warning',
                        BookingStatus::Confirmed, BookingStatus::CheckedIn => 'success',
                        BookingStatus::CheckedOut => 'gray',
                        default => 'danger',
                    }),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => match ($state) {
                        PaymentStatus::VerifiedPaid, PaymentStatus::PaidToChannel => 'success',
                        PaymentStatus::Unpaid => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn (Money $state): string => $state->format())
                    ->alignEnd(),
                TextColumn::make('hold_expires_at')
                    ->label('Hold until')
                    ->dateTime('j M H:i')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('source')
                    ->formatStateUsing(fn (BookingSource $state): string => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->multiple()
                    ->options(collect(BookingStatus::cases())->mapWithKeys(fn (BookingStatus $status): array => [$status->value => $status->label()])->all())
                    ->default([BookingStatus::Hold->value, BookingStatus::Pending->value, BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value]),
                SelectFilter::make('payment_status')
                    ->label('Payment')
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $status): array => [$status->value => $status->label()])->all()),
                Filter::make('stay')
                    ->schema([
                        DatePicker::make('from')->label('Staying from'),
                        DatePicker::make('until')->label('Staying until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $from) => $query->where('departure_date', '>', $from))
                        ->when($data['until'] ?? null, fn (Builder $query, string $until) => $query->where('arrival_date', '<=', $until))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
