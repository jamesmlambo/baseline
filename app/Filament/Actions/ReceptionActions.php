<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Actions\Bookings\ReassignRoom;
use App\Actions\Reception\AddCharge;
use App\Actions\Reception\CheckIn;
use App\Actions\Reception\CheckOut;
use App\Actions\Reception\ExtendStay;
use App\Actions\Reception\ResolveReceptionCode;
use App\Enums\BookingStatus;
use App\Enums\FrontDeskLogType;
use App\Filament\Forms\MoneyInput;
use App\Models\Booking;
use App\Models\FrontDeskLog;
use App\Models\Room;
use App\Services\Availability\AvailabilityService;
use App\Services\Portal\PortalAccess;
use App\Support\Money;
use App\Support\QrCode;
use App\Support\StayDates;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\HtmlString;

/**
 * Reception buttons for the booking screen (REC-03, REC-04, REC-05, INV-07). Each one calls the
 * same action class the tests cover, so the rules cannot be bypassed from the UI.
 */
final class ReceptionActions
{
    public static function checkIn(): Action
    {
        $required = CheckIn::requiredRegisterFields();
        $isRequired = fn (string $field): bool => in_array($field, $required, true);

        return Action::make('checkIn')
            ->label('Check in')
            ->icon('heroicon-o-arrow-right-end-on-rectangle')
            ->color('success')
            ->visible(fn (Booking $record): bool => $record->status === BookingStatus::Confirmed && ! $record->arrival_date->isFuture())
            ->authorize(fn (Booking $record): bool => auth()->user()->can('update', $record))
            ->modalHeading('Check in and guest register')
            ->modalDescription('Which register fields the law requires is still being confirmed. Fields marked * are required by the current setting.')
            ->fillForm(fn (Booking $record): array => [
                'full_name' => $record->registerEntry?->full_name ?? $record->lead_guest_name,
                'number_of_guests' => $record->activeBookingRooms()->get()->sum(fn ($room) => $room->adults + $room->children) ?: null,
                'key_issued' => true,
            ])
            ->schema([
                Section::make('Guest register')
                    ->columns(2)
                    ->schema([
                        TextInput::make('full_name')->label('Full name')->required($isRequired('full_name'))->maxLength(255),
                        Select::make('id_type')->label('ID type')
                            ->options(['sa_id' => 'South African ID', 'passport' => 'Passport', 'other' => 'Other'])
                            ->required($isRequired('id_type')),
                        TextInput::make('id_number')->label('ID / passport number')->required($isRequired('id_number'))->maxLength(64)
                            ->helperText('Stored encrypted.'),
                        TextInput::make('nationality')->required($isRequired('nationality'))->maxLength(64),
                        Textarea::make('home_address')->rows(2)->required($isRequired('home_address'))->columnSpanFull(),
                        TextInput::make('vehicle_registration')->label('Vehicle registration')->required($isRequired('vehicle_registration'))->maxLength(24),
                        TextInput::make('number_of_guests')->integer()->minValue(1)->required($isRequired('number_of_guests')),
                    ]),
                Section::make('Key')
                    ->columns(2)
                    ->schema([
                        Toggle::make('key_issued')->label('Key handed over')->live(),
                        TextInput::make('key_label')->label('Key / card number')->maxLength(60)
                            ->visible(fn (Get $get): bool => (bool) $get('key_issued')),
                    ]),
            ])
            ->action(fn (Booking $record, array $data) => PaymentActions::run(
                fn () => app(CheckIn::class)->handle(
                    $record,
                    auth()->user(),
                    collect($data)->only(['full_name', 'id_type', 'id_number', 'nationality', 'home_address', 'vehicle_registration', 'number_of_guests'])->all(),
                    (bool) ($data['key_issued'] ?? false),
                    $data['key_label'] ?? null,
                ),
                'Guest checked in',
            ));
    }

    public static function checkOut(): Action
    {
        return Action::make('checkOut')
            ->label('Check out')
            ->icon('heroicon-o-arrow-left-start-on-rectangle')
            ->color('warning')
            ->visible(fn (Booking $record): bool => $record->status === BookingStatus::CheckedIn)
            ->authorize(fn (Booking $record): bool => auth()->user()->can('update', $record))
            ->modalDescription(function (Booking $record): string {
                $balance = $record->balanceDue();
                $keys = CheckOut::keysOutstanding($record);
                $lines = [$balance->cents > 0
                    ? "The guest still owes {$balance->format()}. Take payment first; only the owner can check out with money owed."
                    : 'Nothing is owed.'];

                if ($keys > 0) {
                    $lines[] = "{$keys} key(s) not yet returned.";
                }

                return implode(' ', $lines);
            })
            ->schema(fn (Booking $record): array => $record->balanceDue()->cents > 0 && auth()->user()->can('overrideCheckoutBalance', $record)
                ? [Textarea::make('override_reason')->label('Owner override: why may the guest leave owing money?')->rows(2)]
                : [])
            ->action(function (Booking $record, array $data): void {
                PaymentActions::run(function () use ($record, $data): void {
                    $result = app(CheckOut::class)->handle($record, auth()->user(), $data['override_reason'] ?? null);

                    if ($result['keys_outstanding'] > 0) {
                        Notification::make()->title("{$result['keys_outstanding']} key(s) still out")->body('Log the return or the loss on the front-desk log.')->warning()->send();
                    }
                }, 'Guest checked out');
            });
    }

    public static function extendStay(): Action
    {
        return Action::make('extendStay')
            ->label('Extend stay')
            ->icon('heroicon-o-calendar-days')
            ->visible(fn (Booking $record): bool => in_array($record->status, [BookingStatus::Confirmed, BookingStatus::CheckedIn], true))
            ->authorize(fn (Booking $record): bool => auth()->user()->can('update', $record))
            ->modalDescription('The extra nights are priced from the rate engine and billed on a new invoice. If the room is taken, move the guest first.')
            ->schema(fn (Booking $record): array => [
                DatePicker::make('departure')
                    ->label('New departure date')
                    ->native(false)
                    ->minDate($record->departure_date->addDay())
                    ->default($record->departure_date->addDay()->toDateString())
                    ->required(),
            ])
            ->action(fn (Booking $record, array $data) => PaymentActions::run(
                fn () => app(ExtendStay::class)->handle($record, CarbonImmutable::parse($data['departure']), auth()->user()),
                'Stay extended',
            ));
    }

    public static function moveRoom(): Action
    {
        return Action::make('moveRoom')
            ->label('Move room')
            ->icon('heroicon-o-arrows-right-left')
            ->visible(fn (Booking $record): bool => in_array($record->status, [BookingStatus::Hold, BookingStatus::Pending, BookingStatus::Confirmed], true))
            ->authorize(fn (Booking $record): bool => auth()->user()->can('update', $record))
            ->modalDescription('Moves the whole stay. The reason is kept in the audit log. (Moving a guest partway through a stay comes later.)')
            ->schema(fn (Booking $record): array => [
                Select::make('room_id')
                    ->label('Move to')
                    ->options(fn (): array => self::freeRoomsFor($record))
                    ->required(),
                Textarea::make('reason')->required()->rows(2),
            ])
            ->action(function (Booking $record, array $data): void {
                PaymentActions::run(function () use ($record, $data): void {
                    $current = $record->activeBookingRooms()->firstOrFail();
                    app(ReassignRoom::class)->handle($current, Room::query()->findOrFail($data['room_id']), $data['reason'], auth()->user());
                }, 'Booking moved');
            });
    }

    public static function addCharge(): Action
    {
        return Action::make('addCharge')
            ->label('Add charge')
            ->icon('heroicon-o-plus-circle')
            ->visible(fn (Booking $record): bool => in_array($record->status, [BookingStatus::Confirmed, BookingStatus::CheckedIn], true))
            ->authorize(fn (Booking $record): bool => auth()->user()->can('update', $record))
            ->modalDescription('Billed on its own invoice and added to what the guest owes.')
            ->schema([
                TextInput::make('description')->required()->maxLength(255),
                TextInput::make('quantity')->integer()->minValue(1)->default(1)->required(),
                MoneyInput::make('unit_price')->label('Price each')->required(),
            ])
            ->action(fn (Booking $record, array $data) => PaymentActions::run(
                fn () => app(AddCharge::class)->handle(
                    $record,
                    $data['description'],
                    (int) $data['quantity'],
                    $data['unit_price'] instanceof Money ? $data['unit_price'] : Money::fromRands((string) $data['unit_price']),
                    auth()->user(),
                ),
                'Charge added',
            ));
    }

    public static function logEvent(): Action
    {
        return Action::make('logEvent')
            ->label('Log key / items / complaint')
            ->icon('heroicon-o-clipboard-document-list')
            ->authorize(fn (Booking $record): bool => auth()->user()->can('update', $record))
            ->schema([
                Select::make('type')->options(FrontDeskLogType::options())->required()->live(),
                TextInput::make('item')
                    ->label(fn (Get $get): string => $get('type') === FrontDeskLogType::ItemIssued->value ? 'Items (e.g. towels, toiletries)' : 'Key / item')
                    ->maxLength(120),
                TextInput::make('quantity')->integer()->minValue(1)->default(1),
                Textarea::make('details')->rows(2)
                    ->required(fn (Get $get): bool => in_array($get('type'), [FrontDeskLogType::Complaint->value, FrontDeskLogType::Note->value], true)),
            ])
            ->action(fn (Booking $record, array $data) => PaymentActions::run(fn () => FrontDeskLog::query()->create([
                'booking_id' => $record->id,
                'room_id' => $record->activeBookingRooms()->value('room_id'),
                'type' => $data['type'],
                'item' => $data['item'] ?? null,
                'quantity' => max(1, (int) ($data['quantity'] ?? 1)),
                'details' => $data['details'] ?? null,
                'recorded_by' => auth()->id(),
            ]), 'Logged'));
    }

    public static function revokeQr(): Action
    {
        return Action::make('revokeQr')
            ->label('Replace document QR code')
            ->icon('heroicon-o-qr-code')
            ->color('gray')
            ->authorize(fn (Booking $record): bool => auth()->user()->can('update', $record))
            ->requiresConfirmation()
            ->modalDescription('Use if a printed invoice or receipt was lost. The QR on old copies stops working; new PDFs get a new code.')
            ->action(fn (Booking $record) => PaymentActions::run(fn () => ResolveReceptionCode::regenerate($record, auth()->user()), 'QR code replaced'));
    }

    /**
     * GST-03: a 30-minute portal link for a guest who cannot receive the code, issued after reception checks ID.
     */
    public static function portalLink(): Action
    {
        return Action::make('portalLink')
            ->label('Guest portal link (after ID check)')
            ->icon('heroicon-o-device-phone-mobile')
            ->visible(fn (Booking $record): bool => $record->status === BookingStatus::CheckedIn)
            ->authorize(fn (Booking $record): bool => auth()->user()->can('update', $record))
            ->schema([
                TextInput::make('identity_check')
                    ->label('How did you confirm who the guest is?')
                    ->placeholder('e.g. Checked passport against booking name')
                    ->required()
                    ->maxLength(255),
            ])
            ->modalDescription('Only for the guest staying in this booking. The link works for 30 minutes; let the guest scan the QR from this screen.')
            ->action(function (Booking $record, array $data, Action $action): void {
                try {
                    $link = app(PortalAccess::class)->receptionLink($record, auth()->user(), $data['identity_check']);
                } catch (AuthorizationException|\InvalidArgumentException $exception) {
                    Notification::make()->title('Not done')->body($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Portal link ready (30 minutes)')->success()->send();
                $action->getLivewire()->replaceMountedAction('showPortalLink', ['link' => $link]);
            });
    }

    /**
     * Shows the link from portalLink() as a QR the guest scans from the reception screen.
     * The page registers it with a showPortalLinkAction() method.
     */
    public static function showPortalLink(): Action
    {
        return Action::make('showPortalLink')
            ->modalHeading('Guest portal link')
            ->modalDescription('Let the guest scan this with their phone. It works once signed in, for 30 minutes.')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Done')
            ->schema(fn (array $arguments): array => [
                Text::make(new HtmlString(
                    '<div style="text-align:center"><img src="'.QrCode::pngDataUri((string) ($arguments['link'] ?? ''), 240).'" alt="Guest portal QR code" width="240" height="240" style="margin:0 auto">'
                    .'<p style="margin-top:.5rem;word-break:break-all;font-size:.75rem">'.e((string) ($arguments['link'] ?? '')).'</p></div>',
                )),
            ]);
    }

    public static function endPortalAccess(): Action
    {
        return Action::make('endPortalAccess')
            ->label('End guest portal access')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (Booking $record): bool => $record->status === BookingStatus::CheckedIn)
            ->authorize(fn (Booking $record): bool => auth()->user()->can('update', $record))
            ->schema([Textarea::make('reason')->required()->rows(2)->placeholder('e.g. Guest reported a lost phone')])
            ->modalDescription('Signs every phone out of this stay\'s portal. The guest can sign in again with a new code.')
            ->action(fn (Booking $record, array $data) => PaymentActions::run(fn () => app(PortalAccess::class)->endAccess($record, auth()->user(), $data['reason']), 'Portal access ended'));
    }

    /**
     * @return array<int, string>
     */
    private static function freeRoomsFor(Booking $booking): array
    {
        $current = $booking->activeBookingRooms()->with('accommodationType')->first();

        if ($current === null) {
            return [];
        }

        $stay = new StayDates($booking->arrival_date, $booking->departure_date);
        $availability = app(AvailabilityService::class);

        return Room::query()->active()->with('accommodationType')->orderBy('sort_order')->get()
            ->filter(fn (Room $room): bool => $room->id !== $current->room_id && $availability->isRoomAvailable($room, $stay))
            ->filter(fn (Room $room): bool => $room->accommodationType->max_occupancy >= $current->adults + $current->children)
            ->mapWithKeys(fn (Room $room): array => [$room->id => "{$room->name} ({$room->accommodationType->name})"])
            ->all();
    }
}
