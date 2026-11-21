<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Widgets\Concerns\FrontDeskOnly;
use App\Models\Booking;
use App\Services\Reception\ReceptionOverview;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Today's arrivals, departures or in-house guests on the dashboard (REC-01).
 */
abstract class FrontDeskBookingsTable extends TableWidget
{
    use FrontDeskOnly;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return Builder<Booking>
     */
    abstract protected function bookings(ReceptionOverview $overview): Builder;

    abstract protected function emptyMessage(): string;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->bookings(app(ReceptionOverview::class))->with('activeBookingRooms.room'))
            ->paginated(false)
            ->emptyStateHeading($this->emptyMessage())
            ->columns([
                TextColumn::make('reference')->weight('bold'),
                TextColumn::make('lead_guest_name')->label('Guest'),
                TextColumn::make('room')
                    ->state(fn (Booking $record): string => $record->activeBookingRooms->map(fn ($room) => $room->room->name)->implode(', ') ?: '—'),
                TextColumn::make('nights')->state(fn (Booking $record): int => $record->nights()),
                TextColumn::make('status')->badge()->formatStateUsing(fn (BookingStatus $state): string => $state->label()),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state === PaymentStatus::VerifiedPaid ? 'success' : 'warning'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Booking $record): string => BookingResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
