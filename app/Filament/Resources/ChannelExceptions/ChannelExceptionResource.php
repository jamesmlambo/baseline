<?php

declare(strict_types=1);

namespace App\Filament\Resources\ChannelExceptions;

use App\Actions\Channels\ImportChannelReservation;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\ChannelExceptions\Pages\ListChannelExceptions;
use App\Models\ChannelException;
use App\Services\Channels\ChannelReservationData;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The channel exception queue (DEC-02, CHN-03): nothing from a channel is ever overbooked or
 * dropped silently; whatever can't be placed safely waits here for a person.
 */
class ChannelExceptionResource extends Resource
{
    protected static ?string $model = ChannelException::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Bookings';

    protected static ?string $navigationLabel = 'Channel problems';

    protected static ?string $modelLabel = 'channel problem';

    protected static ?int $navigationSort = 41;

    public static function getNavigationBadge(): ?string
    {
        $open = ChannelException::query()->open()->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('60s')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['channel', 'reservation.booking', 'room']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->since()->sortable(),
                TextColumn::make('channel.name')->label('Channel')->placeholder('—'),
                TextColumn::make('kind')->badge()->color('danger')->formatStateUsing(fn (ChannelException $record): string => $record->kindLabel()),
                TextColumn::make('summary')->wrap()->description(fn (ChannelException $record): ?string => $record->details),
                TextColumn::make('reservation.booking.reference')->label('Booking')->placeholder('—')
                    ->url(fn (ChannelException $record): ?string => $record->reservation?->booking ? BookingResource::getUrl('view', ['record' => $record->reservation->booking]) : null),
                TextColumn::make('status')->badge()->color(fn (string $state): string => $state === 'open' ? 'warning' : 'gray')
                    ->description(fn (ChannelException $record): ?string => $record->resolution),
            ])
            ->filters([
                SelectFilter::make('status')->options(['open' => 'Open', 'resolved' => 'Resolved'])->default('open'),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('Try again')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (ChannelException $record): bool => $record->status === 'open' && in_array($record->kind, [ChannelException::NO_ROOM, ChannelException::UNMAPPED], true) && $record->reservation !== null)
                    ->authorize(fn (ChannelException $record): bool => auth()->user()->can('update', $record))
                    ->modalDescription('Places the reservation again with the details we received (e.g. after freeing a room or linking the room code).')
                    ->requiresConfirmation()
                    ->action(function (ChannelException $record): void {
                        $reservation = $record->reservation;
                        $stay = $reservation->payload['stay'] ?? [];

                        app(ImportChannelReservation::class)->handle($record->channel, new ChannelReservationData(
                            externalReference: $reservation->external_reference,
                            status: ChannelReservationData::NEW,
                            roomCode: (string) ($stay['room_code'] ?? ''),
                            arrival: CarbonImmutable::parse((string) ($stay['arrival'] ?? 'today'), 'Africa/Johannesburg'),
                            departure: CarbonImmutable::parse((string) ($stay['departure'] ?? 'tomorrow'), 'Africa/Johannesburg'),
                            adults: (int) ($stay['adults'] ?? 1),
                            children: (int) ($stay['children'] ?? 0),
                            guestName: (string) ($reservation->payload['guest'] ?? 'Channel guest'),
                            totalCents: $reservation->channel_total_cents?->cents,
                            raw: $reservation->payload['raw'] ?? [],
                        ));

                        $placed = $reservation->fresh()->booking_id !== null;
                        Notification::make()->title($placed ? 'Booked' : 'Still no room')->color($placed ? 'success' : 'warning')->send();
                    }),
                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ChannelException $record): bool => $record->status === 'open')
                    ->authorize(fn (ChannelException $record): bool => auth()->user()->can('update', $record))
                    ->schema([Textarea::make('resolution')->label('What was done')->required()->rows(2)])
                    ->action(fn (ChannelException $record, array $data) => $record->forceFill([
                        'status' => 'resolved',
                        'resolution' => trim($data['resolution']),
                        'resolved_by' => auth()->id(),
                        'resolved_at' => now(),
                    ])->save()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListChannelExceptions::route('/')];
    }
}
