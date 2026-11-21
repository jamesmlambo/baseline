<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceBookings\Pages;

use App\Enums\BookingStatus;
use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\ServiceBookings\ServiceBookingResource;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Services\Leisure\ServiceAvailability;
use App\Services\Leisure\ServiceBookings;
use App\Support\Features;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class ListServiceBookings extends ListRecords
{
    protected static string $resource = ServiceBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newBooking')
                ->label('New leisure booking')
                ->icon('heroicon-o-plus')
                ->authorize(fn (): bool => auth()->user()->can('create', ServiceBooking::class))
                ->schema([
                    Select::make('service_id')->label('Service')
                        ->options(fn (): array => Service::query()->active()->get()->mapWithKeys(fn (Service $service): array => [$service->id => "{$service->name} — {$service->priceLabel()}"])->all())
                        ->required()->live()->afterStateUpdated(fn (Set $set) => $set('start', null)),
                    DatePicker::make('date')->required()->default(today())->minDate(today())->live()->afterStateUpdated(fn (Set $set) => $set('start', null)),
                    TextInput::make('party_size')->label('People')->integer()->minValue(1)->default(1)->required()->live(onBlur: true),
                    Select::make('start')->label('Time')
                        ->options(fn (Get $get): array => self::slotOptions($get('service_id'), $get('date'), (int) $get('party_size')))
                        ->required()
                        ->helperText('Only free times are shown.'),
                    Select::make('booking_id')->label('In-house guest (optional)')
                        ->options(fn (): array => Booking::query()->where('status', BookingStatus::CheckedIn)->orderBy('lead_guest_name')->get()
                            ->mapWithKeys(fn (Booking $booking): array => [$booking->id => "{$booking->lead_guest_name} ({$booking->reference})"])->all())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $booking = $state ? Booking::query()->find($state) : null;
                            $set('customer_name', $booking?->lead_guest_name);
                            $set('charge_to_room', $booking !== null);
                        }),
                    Toggle::make('charge_to_room')->label('Charge to the room bill')->visible(fn (Get $get): bool => filled($get('booking_id'))),
                    TextInput::make('customer_name')->label('Name')->required()->maxLength(255),
                    TextInput::make('customer_phone')->label('Phone')->tel()->maxLength(32),
                    Checkbox::make('indemnity')->label('Guest has read and accepted the safety notice')
                        ->visible(fn (Get $get): bool => Features::enabled(Features::INDEMNITY) && (bool) Service::query()->whereKey($get('service_id'))->value('requires_indemnity')),
                    Textarea::make('notes')->rows(2),
                ])
                ->action(fn (array $data) => PaymentActions::run(fn () => app(ServiceBookings::class)->book(
                    Service::query()->findOrFail($data['service_id']),
                    CarbonImmutable::parse($data['start']),
                    (int) $data['party_size'],
                    ['name' => $data['customer_name'], 'phone' => $data['customer_phone'] ?? null, 'notes' => $data['notes'] ?? null],
                    filled($data['booking_id'] ?? null) ? Booking::query()->find($data['booking_id']) : null,
                    (bool) ($data['charge_to_room'] ?? false),
                    ServiceBooking::SOURCE_RECEPTION,
                    auth()->user(),
                    (bool) ($data['indemnity'] ?? false),
                ), 'Booked')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function slotOptions(mixed $serviceId, mixed $date, int $partySize): array
    {
        $service = filled($serviceId) ? Service::query()->find($serviceId) : null;

        if ($service === null || blank($date)) {
            return [];
        }

        return collect(app(ServiceAvailability::class)->slots($service, CarbonImmutable::parse((string) $date, 'Africa/Johannesburg'), max(1, $partySize)))
            ->mapWithKeys(fn (CarbonImmutable $slot): array => [$slot->toIso8601String() => $slot->setTimezone('Africa/Johannesburg')->format('H:i')])
            ->all();
    }
}
