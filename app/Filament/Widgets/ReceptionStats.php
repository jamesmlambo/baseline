<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\RoomCalendarPage;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\GuestRequests\GuestRequestResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\RoomBlocks\RoomBlockResource;
use App\Filament\Widgets\Concerns\FrontDeskOnly;
use App\Services\Reception\ReceptionOverview;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Reception at a glance (REC-01).
 */
class ReceptionStats extends StatsOverviewWidget
{
    use FrontDeskOnly;

    protected static ?int $sort = -10;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $overview = app(ReceptionOverview::class);
        $efts = $overview->eftsAwaitingVerification();
        $exceptions = $overview->paymentExceptions();
        $expiring = $overview->holdsExpiringSoon();
        $complaints = $overview->openComplaints();
        $portalWaiting = $overview->guestPortalWaiting();

        return [
            Stat::make('Arrivals today', $overview->arrivals()->count())
                ->icon('heroicon-o-arrow-right-end-on-rectangle'),
            Stat::make('Departures today', $overview->departures()->count())
                ->icon('heroicon-o-arrow-left-start-on-rectangle'),
            Stat::make('In house', $overview->inHouse()->count())
                ->icon('heroicon-o-home'),
            Stat::make('Free rooms tonight', $overview->freeRoomsTonight())
                ->icon('heroicon-o-key')
                ->description($overview->roomsBlockedToday().' blocked')
                ->url(RoomCalendarPage::getUrl()),
            Stat::make('Guests owing money', $overview->guestsWithBalance())
                ->description('Arriving or in house')
                ->color($overview->guestsWithBalance() > 0 ? 'warning' : 'success'),
            Stat::make('EFTs to verify', $efts)
                ->description($exceptions > 0 ? "{$exceptions} payment(s) need attention" : 'No payment problems')
                ->color($efts + $exceptions > 0 ? 'warning' : 'success')
                ->url(PaymentResource::getUrl()),
            Stat::make('Holds expiring in 2 h', $expiring)
                ->color($expiring > 0 ? 'warning' : 'gray')
                ->url(BookingResource::getUrl()),
            Stat::make('Guest portal waiting', $portalWaiting)
                ->description('New requests and unanswered messages')
                ->icon('heroicon-o-bell-alert')
                ->color($portalWaiting > 0 ? 'danger' : 'success')
                ->url(GuestRequestResource::getUrl()),
            Stat::make('Open complaints / lost keys', $complaints)
                ->color($complaints > 0 ? 'danger' : 'success')
                ->description($overview->roomsBlockedToday().' room(s) out of service')
                ->url(RoomBlockResource::getUrl()),
        ];
    }
}
