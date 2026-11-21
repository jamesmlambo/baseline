<?php

declare(strict_types=1);

namespace App\Filament\Resources\GuestRequests;

use App\Enums\GuestRequestStatus;
use App\Enums\GuestRequestType;
use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\GuestRequests\Pages\ListGuestRequests;
use App\Models\GuestRequest;
use App\Services\Portal\GuestRequests;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Reception's queue of guest-portal requests (GST-07/08). Refreshes every 15 seconds.
 */
class GuestRequestResource extends Resource
{
    protected static ?string $model = GuestRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Front desk';

    protected static ?string $navigationLabel = 'Guest requests';

    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        $new = GuestRequest::query()->where('status', GuestRequestStatus::New)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('15s')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['booking', 'room']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->since()->sortable()
                    ->description(fn (GuestRequest $record): string => $record->created_at->format('H:i')),
                TextColumn::make('room.name')->label('Room')->weight('bold')->placeholder('—'),
                TextColumn::make('booking.lead_guest_name')->label('Guest')
                    ->url(fn (GuestRequest $record): string => BookingResource::getUrl('view', ['record' => $record->booking])),
                TextColumn::make('type')->badge()->formatStateUsing(fn (GuestRequestType $state): string => $state->label()),
                TextColumn::make('what')
                    ->label('Details')
                    ->wrap()
                    ->state(fn (GuestRequest $record): string => trim(collect($record->items ?? [])->map(fn (array $item): string => $item['quantity'].' × '.$item['name'])->implode(', ').' '.($record->details ?? '')) ?: '—'),
                TextColumn::make('total_cents')->label('Total')
                    ->formatStateUsing(fn (Money $state): string => $state->isZero() ? '—' : $state->format()),
                IconColumn::make('charge_to_room')->label('To bill')->boolean()->toggleable(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (GuestRequestStatus $state): string => $state->staffLabel())
                    ->color(fn (GuestRequestStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->multiple()
                    ->options(GuestRequestStatus::options())
                    ->default([GuestRequestStatus::New->value, GuestRequestStatus::Acknowledged->value]),
                SelectFilter::make('type')->options(GuestRequestType::options()),
            ])
            ->recordActions([
                Action::make('acknowledge')
                    ->label('On it')
                    ->icon('heroicon-o-hand-thumb-up')
                    ->color('warning')
                    ->visible(fn (GuestRequest $record): bool => $record->status === GuestRequestStatus::New)
                    ->authorize(fn (GuestRequest $record): bool => auth()->user()->can('update', $record))
                    ->action(fn (GuestRequest $record) => PaymentActions::run(fn () => app(GuestRequests::class)->acknowledge($record, auth()->user()), 'Guest will see "On its way"')),
                Action::make('complete')
                    ->label('Done')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (GuestRequest $record): bool => $record->status->isOpen() && $record->type !== GuestRequestType::Checkout)
                    ->authorize(fn (GuestRequest $record): bool => auth()->user()->can('update', $record))
                    ->requiresConfirmation(fn (GuestRequest $record): bool => $record->charge_to_room && ! $record->total_cents->isZero())
                    ->modalDescription(fn (GuestRequest $record): ?string => $record->charge_to_room && ! $record->total_cents->isZero()
                        ? "{$record->total_cents->format()} will be added to the guest's bill."
                        : null)
                    ->action(fn (GuestRequest $record) => PaymentActions::run(fn () => app(GuestRequests::class)->complete($record, auth()->user()), 'Marked as done')),
                Action::make('openBooking')
                    ->label('Check out')
                    ->icon('heroicon-o-arrow-left-start-on-rectangle')
                    ->visible(fn (GuestRequest $record): bool => $record->type === GuestRequestType::Checkout && $record->status->isOpen())
                    ->url(fn (GuestRequest $record): string => BookingResource::getUrl('view', ['record' => $record->booking])),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (GuestRequest $record): bool => $record->status === GuestRequestStatus::New)
                    ->authorize(fn (GuestRequest $record): bool => auth()->user()->can('update', $record))
                    ->requiresConfirmation()
                    ->action(fn (GuestRequest $record) => PaymentActions::run(fn () => app(GuestRequests::class)->cancel($record, auth()->user()), 'Cancelled')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuestRequests::route('/'),
        ];
    }
}
