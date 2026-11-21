<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bookings;

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Bookings\RelationManagers\FrontDeskLogsRelationManager;
use App\Filament\Resources\Bookings\RelationManagers\MessagesRelationManager;
use App\Filament\Resources\Bookings\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Filament\Resources\Bookings\Schemas\BookingInfolist;
use App\Filament\Resources\Bookings\Tables\BookingsTable;
use App\Models\Booking;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Bookings';

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return BookingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BookingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BookingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
            FrontDeskLogsRelationManager::class,
            MessagesRelationManager::class,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $holds = Booking::query()->where('status', BookingStatus::Hold)->count();

        return $holds > 0 ? (string) $holds : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Bookings on hold, awaiting payment';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['reference', 'lead_guest_name'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
            'create' => CreateBooking::route('/create'),
            'view' => ViewBooking::route('/{record}'),
        ];
    }
}
