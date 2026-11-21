<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bookings\Pages;

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\ReinstateBooking;
use App\Enums\BookingStatus;
use App\Filament\Actions\PaymentActions;
use App\Filament\Actions\ReceptionActions;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Services\Bookings\BookingStateMachine;
use App\Services\Finance\DocumentIssuer;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ReceptionActions::checkIn()->after(fn () => $this->refreshRecord()),
            ReceptionActions::checkOut()->after(fn () => $this->refreshRecord()),
            PaymentActions::record()
                ->visible(fn (Booking $record): bool => $record->status->holdsInventory())
                ->after(fn () => $this->refreshRecord()),
            Action::make('reinstate')
                ->label('Reinstate')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->authorize(fn (Booking $record): bool => auth()->user()->can('reinstate', $record))
                ->requiresConfirmation()
                ->modalDescription('Re-claims the same room (or another of the same type) if it is still free, and confirms the booking. Use this when payment arrived after the hold expired.')
                ->action(fn (Booking $record) => PaymentActions::run(fn () => app(ReinstateBooking::class)->handle($record, auth()->user()), 'Booking reinstated'))
                ->after(fn () => $this->refreshRecord()),
            ActionGroup::make([
                ReceptionActions::extendStay()->after(fn () => $this->refreshRecord()),
                ReceptionActions::moveRoom()->after(fn () => $this->refreshRecord()),
                ReceptionActions::addCharge()->after(fn () => $this->refreshRecord()),
                ReceptionActions::logEvent()->after(fn () => $this->refreshRecord()),
            ])->label('Front desk')->button()->icon('heroicon-o-bell')->color('gray'),
            ActionGroup::make([
                Action::make('quotation')
                    ->label('Issue quotation')
                    ->icon('heroicon-o-document-text')
                    ->authorize(fn (Booking $record): bool => auth()->user()->can('update', $record))
                    ->requiresConfirmation()
                    ->action(fn (Booking $record) => PaymentActions::run(fn () => app(DocumentIssuer::class)->quotationForBooking($record, auth()->user()), 'Quotation issued')),
                ReceptionActions::revokeQr(),
                Action::make('confirmWithoutPayment')
                    ->label('Confirm without payment')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('warning')
                    ->authorize(fn (Booking $record): bool => auth()->user()->can('confirmWithoutPayment', $record))
                    ->schema([Textarea::make('reason')->label('Why (e.g. approved corporate account)')->required()->rows(2)])
                    ->modalDescription('Owner override (PEND-06). It is recorded in the booking history and audit log.')
                    ->action(fn (Booking $record, array $data) => PaymentActions::run(
                        fn () => app(BookingStateMachine::class)->transition($record, BookingStatus::Confirmed, 'Owner override: '.$data['reason'], auth()->user()),
                        'Booking confirmed',
                    ))
                    ->after(fn () => $this->refreshRecord()),
                Action::make('cancel')
                    ->label('Cancel booking')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->authorize(fn (Booking $record): bool => auth()->user()->can('cancel', $record))
                    ->schema([Textarea::make('reason')->required()->rows(2)])
                    ->modalDescription('The room is released immediately. Any refund must be requested separately on the payment.')
                    ->action(fn (Booking $record, array $data) => PaymentActions::run(fn () => app(CancelBooking::class)->handle($record, $data['reason'], auth()->user()), 'Booking cancelled'))
                    ->after(fn () => $this->refreshRecord()),
            ])->label('More')->button()->color('gray'),
        ];
    }

    private function refreshRecord(): void
    {
        $this->record->refresh()->load(['activeBookingRooms.room', 'activeBookingRooms.accommodationType', 'extras', 'statusHistory', 'guest', 'invoices']);
        $this->dispatch('$refresh');
    }
}
