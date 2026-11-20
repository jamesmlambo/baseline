<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bookings\Pages;

use App\Actions\Bookings\CreateReservation;
use App\Actions\Bookings\Data\ReservationRequest;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Exceptions\RoomUnavailableException;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\AccommodationType;
use App\Models\Room;
use App\Support\StayDates;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $type = AccommodationType::query()->findOrFail($data['accommodation_type_id']);

        try {
            return app(CreateReservation::class)->handle(new ReservationRequest(
                type: $type,
                stay: new StayDates($data['arrival'], $data['departure']),
                adults: (int) $data['adults'],
                children: (int) ($data['children'] ?? 0),
                firstName: $data['first_name'],
                lastName: $data['last_name'],
                email: $data['email'] ?? null,
                phone: $data['phone'] ?? null,
                source: BookingSource::from($data['source']),
                status: BookingStatus::Hold,
                extraQuantities: array_fill_keys(array_map('intval', $data['extras'] ?? []), 0),
                specialRequests: $data['special_requests'] ?? null,
                termsAccepted: false,
                marketingConsent: (bool) ($data['marketing_consent'] ?? false),
                holdExpiresAt: CarbonImmutable::parse($data['hold_expires_at']),
                room: filled($data['room_id'] ?? null) ? Room::query()->find($data['room_id']) : null,
                createdBy: auth()->user(),
            ));
        } catch (RoomUnavailableException|InvalidArgumentException $exception) {
            Notification::make()->title('Booking not created')->body($exception->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
