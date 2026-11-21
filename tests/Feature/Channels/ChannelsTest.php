<?php

declare(strict_types=1);

require_once __DIR__.'/../Finance/FinanceTestHelpers.php';
require_once __DIR__.'/../Bookings/BookingTestHelpers.php';

use App\Actions\Channels\ImportChannelReservation;
use App\Actions\Reception\CheckIn;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoomBlockReason;
use App\Enums\StaffRole;
use App\Filament\Resources\ChannelExceptions\Pages\ListChannelExceptions;
use App\Filament\Resources\Channels\Pages\EditChannel;
use App\Filament\Resources\Channels\Pages\ListChannels;
use App\Models\AccommodationType;
use App\Models\Booking;
use App\Models\Channel;
use App\Models\ChannelException;
use App\Models\ChannelReservation;
use App\Models\ChannelRoomType;
use App\Models\IcalLink;
use App\Models\RoomBlock;
use App\Notifications\StaffAlert;
use App\Services\Channels\ChannelDrivers;
use App\Services\Channels\ChannelManager;
use App\Services\Channels\ChannelReservationData;
use App\Services\Channels\ChannelSync;
use App\Services\Inventory\RoomNightLedger;
use App\Services\Payments\PaymentLedger;
use App\Services\Reports\ReportMetrics;
use Carbon\CarbonImmutable;
use Database\Seeders\ChannelSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    seedFinanceSettings();
    Mail::fake();
    $this->seed(ChannelSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2027-06-01 10:00', 'Africa/Johannesburg'));
});

function bookingCom(): Channel
{
    return Channel::query()->where('code', 'booking_com')->sole();
}

/**
 * A one-room type mapped to Booking.com code "DBL".
 */
function mappedType(): AccommodationType
{
    $type = financeRoomType();
    ChannelRoomType::query()->create(['channel_id' => bookingCom()->id, 'accommodation_type_id' => $type->id, 'external_code' => 'DBL']);

    return $type;
}

function otaReservation(string $reference = 'BC-1001', string $status = ChannelReservationData::NEW, string $arrival = '2027-06-10', string $departure = '2027-06-12', string $code = 'DBL', ?int $total = 180_000): ChannelReservationData
{
    return new ChannelReservationData(
        externalReference: $reference,
        status: $status,
        roomCode: $code,
        arrival: CarbonImmutable::parse($arrival, 'Africa/Johannesburg'),
        departure: CarbonImmutable::parse($departure, 'Africa/Johannesburg'),
        adults: 2,
        children: 0,
        guestName: 'Pieter OTA',
        email: 'pieter@example.test',
        totalCents: $total,
    );
}

function importOta(ChannelReservationData $data): ChannelReservation
{
    return app(ImportChannelReservation::class)->handle(bookingCom(), $data);
}

// --- Reservation import (CHN-03, DEC-02) ------------------------------------------------

it('CHN-03 places an OTA reservation with a room assigned, at the channel\'s price, once', function (): void {
    mappedType();

    $first = importOta(otaReservation());
    importOta(otaReservation());

    $booking = Booking::query()->sole();
    expect($first->status)->toBe(ChannelReservation::BOOKED)
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->source)->toBe(BookingSource::BookingCom)
        ->and($booking->external_reference)->toBe('BC-1001')
        ->and($booking->activeBookingRooms()->count())->toBe(1)
        ->and($booking->balanceDue()->cents)->toBe(180_000)
        ->and(ChannelReservation::query()->count())->toBe(1);
});

it('DEC-02 never overbooks: with no room free the reservation waits in the exception queue and staff are alerted', function (): void {
    Notification::fake();
    $type = mappedType();
    $receptionist = staffUser(StaffRole::Receptionist);
    $ours = bookType($type, stay('2027-06-10', '2027-06-12'));

    $reservation = importOta(otaReservation());

    expect($reservation->status)->toBe(ChannelReservation::EXCEPTION)
        ->and($reservation->booking_id)->toBeNull()
        ->and(Booking::query()->count())->toBe(1)
        ->and(ChannelException::query()->open()->sole()->kind)->toBe(ChannelException::NO_ROOM);
    Notification::assertSentTo($receptionist, StaffAlert::class, fn (StaffAlert $alert): bool => $alert->urgent);

    $ours->forceFill(['status' => BookingStatus::Cancelled])->save();
    $ours->bookingRooms()->each(fn ($bookingRoom) => app(RoomNightLedger::class)->releaseBookingRoom($bookingRoom, 'cancelled'));
    importOta(otaReservation());

    expect($reservation->fresh()->status)->toBe(ChannelReservation::BOOKED)
        ->and(ChannelException::query()->open()->count())->toBe(0);
});

it('CHN-03 queues reservations for room codes that are not linked', function (): void {
    mappedType();

    importOta(otaReservation(code: 'SUITE'));

    expect(ChannelException::query()->sole()->kind)->toBe(ChannelException::UNMAPPED)
        ->and(Booking::query()->count())->toBe(0);
});

it('CHN-03 sends date changes to a person instead of moving the booking, and raises them once', function (): void {
    mappedType();
    importOta(otaReservation());

    importOta(otaReservation(status: ChannelReservationData::MODIFIED, arrival: '2027-06-11', departure: '2027-06-13'));
    importOta(otaReservation(status: ChannelReservationData::MODIFIED, arrival: '2027-06-11', departure: '2027-06-13'));

    expect(Booking::query()->sole()->arrival_date->toDateString())->toBe('2027-06-10')
        ->and(ChannelException::query()->where('kind', ChannelException::MODIFIED)->count())->toBe(1);
});

it('CHN-03 cancels the booking and frees the room when the channel cancels', function (): void {
    $type = mappedType();
    importOta(otaReservation());

    importOta(otaReservation(status: ChannelReservationData::CANCELLED));

    expect(Booking::query()->sole()->status)->toBe(BookingStatus::Cancelled)
        ->and(bookType($type, stay('2027-06-10', '2027-06-12'))->status)->toBe(BookingStatus::Confirmed);
});

it('CHN-03 does not cancel a guest who is already in house', function (): void {
    mappedType();
    importOta(otaReservation(arrival: '2027-06-01', departure: '2027-06-03'));
    $booking = Booking::query()->sole();
    app(PaymentLedger::class)->recordAtReception($booking, PaymentMethod::Cash, $booking->balanceDue(), staffUser(StaffRole::Receptionist));
    app(CheckIn::class)->handle($booking->fresh(), staffUser(StaffRole::Receptionist), ['full_name' => 'Pieter OTA']);

    importOta(otaReservation(status: ChannelReservationData::CANCELLED, arrival: '2027-06-01', departure: '2027-06-03'));

    expect($booking->fresh()->status)->toBe(BookingStatus::CheckedIn)
        ->and(ChannelException::query()->sole()->kind)->toBe(ChannelException::CANCEL_FAILED);
});

// --- Sync and monitoring (CHN-01, CHN-04) -----------------------------------------------

it('CHN-01 logs syncs through the null driver as not connected and never as synced', function (): void {
    $channel = bookingCom();
    $channel->update(['is_enabled' => true]);

    $run = app(ChannelSync::class)->sync($channel);

    expect($run->status)->toBe('not_connected')
        ->and($channel->fresh()->last_successful_sync_at)->toBeNull()
        ->and($channel->fresh()->isStale())->toBeTrue();
});

it('CHN-04 records a good sync from a connected driver, and a failure with its error', function (): void {
    mappedType();
    $channel = bookingCom();
    $channel->update(['is_enabled' => true, 'stale_after_minutes' => 60]);
    $connected = new class implements ChannelManager
    {
        public bool $fail = false;

        public function isConnected(): bool
        {
            return true;
        }

        public function fetchReservations(Channel $channel, CarbonImmutable $since): iterable
        {
            if ($this->fail) {
                throw new RuntimeException('Provider timed out');
            }

            return [otaReservation()];
        }

        public function pushAvailability(Channel $channel, AccommodationType $type, array $freeRoomsByDate): void {}

        public function pushRates(Channel $channel, AccommodationType $type, array $rateCentsByDate): void {}
    };
    app()->instance(ChannelDrivers::class, new class($connected) extends ChannelDrivers
    {
        public function __construct(private readonly ChannelManager $manager) {}

        public function for(Channel $channel): ChannelManager
        {
            return $this->manager;
        }
    });

    $ok = app(ChannelSync::class)->sync($channel);
    expect($ok->status)->toBe('ok')->and($ok->items)->toBe(1)
        ->and(Booking::query()->count())->toBe(1)
        ->and($channel->fresh()->isStale())->toBeFalse();

    $connected->fail = true;
    $failed = app(ChannelSync::class)->sync($channel->fresh());
    expect($failed->status)->toBe('failed')->and($channel->fresh()->last_error)->toBe('Provider timed out');

    $this->travel(61)->minutes();
    expect($channel->fresh()->isStale())->toBeTrue();
});

// --- iCal fallback (CHN-02) -------------------------------------------------------------

function icalLink(bool $export = true): IcalLink
{
    $type = financeRoomType();

    return IcalLink::query()->create(['channel_id' => bookingCom()->id, 'room_id' => $type->rooms()->sole()->id, 'export_enabled' => $export]);
}

function feed(string ...$events): string
{
    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n".implode('', $events)."END:VCALENDAR\r\n";
}

function vevent(string $uid, string $start, string $end): string
{
    return "BEGIN:VEVENT\r\nUID:{$uid}\r\nDTSTART;VALUE=DATE:{$start}\r\nDTEND;VALUE=DATE:{$end}\r\nSUMMARY:CLOSED - Not available\r\nEND:VEVENT\r\n";
}

it('CHN-02 shares a room\'s taken dates as iCal only when switched on', function (): void {
    $off = icalLink(export: false);
    $this->get('/ical/'.$off->export_token.'.ics')->assertNotFound();

    $link = icalLink();
    bookType($link->room->accommodationType, stay('2027-06-10', '2027-06-12'), room: $link->room);

    $this->get($link->exportUrl())
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
        ->assertSee('DTSTART;VALUE=DATE:20270610', false)
        ->assertSee('DTEND;VALUE=DATE:20270612', false)
        ->assertDontSee('Demo Guest');
});

it('CHN-02 blocks dates from a channel\'s calendar, releases removed ones, and is safe to repeat', function (): void {
    $link = icalLink();
    $sync = app(ChannelSync::class);

    expect($sync->importIcal($link, feed(vevent('a1', '20270615', '20270618'))))->toMatchArray(['added' => 1, 'clashes' => 0])
        ->and($sync->importIcal($link, feed(vevent('a1', '20270615', '20270618'))))->toMatchArray(['added' => 0, 'removed' => 0]);

    $block = RoomBlock::query()->sole();
    expect($block->reason)->toBe(RoomBlockReason::Channel)->and($block->isActive())->toBeTrue();

    $this->get($link->exportUrl())->assertDontSee('20270615', false);

    $sync->importIcal($link, feed());
    expect($block->fresh()->isActive())->toBeFalse();
});

it('CHN-02 raises a clash instead of double-booking when the channel calendar overlaps our booking', function (): void {
    Notification::fake();
    $link = icalLink();
    bookType($link->room->accommodationType, stay('2027-06-10', '2027-06-12'), room: $link->room);

    $counts = app(ChannelSync::class)->importIcal($link, feed(vevent('b1', '20270611', '20270613')));

    expect($counts['clashes'])->toBe(1)
        ->and(ChannelException::query()->sole()->kind)->toBe(ChannelException::ICAL_CLASH)
        ->and(RoomBlock::query()->count())->toBe(0);
});

it('CHN-02 does not fetch calendars from channels until that is approved', function (): void {
    $link = icalLink();
    $link->update(['import_url' => 'https://example.test/calendar.ics']);

    expect(fn () => app(ChannelSync::class)->fetchIcal($link))->toThrow(RuntimeException::class, 'switched off');
});

it('REP-01 counts nights taken on other channels separately from rooms out of service', function (): void {
    $link = icalLink();
    $before = app(ReportMetrics::class)->forPeriod(CarbonImmutable::parse('2027-06-15'), CarbonImmutable::parse('2027-06-15'))['rooms'];
    app(ChannelSync::class)->importIcal($link, feed(vevent('c1', '20270615', '20270616')));

    $after = app(ReportMetrics::class)->forPeriod(CarbonImmutable::parse('2027-06-15'), CarbonImmutable::parse('2027-06-15'))['rooms'];

    expect($after['rooms_available'])->toBe($before['rooms_available'])
        ->and($after['nights_blocked'])->toBe(0)
        ->and($after['nights_other_channels'])->toBe(1);
});

// --- Screens ----------------------------------------------------------------------------

it('CHN-04 says plainly that no channel manager is connected', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staffUser(StaffRole::Manager));

    Livewire::test(ListChannels::class)
        ->assertSee('No channel manager is connected yet')
        ->assertSee('Booking.com')
        ->assertSee('Off');
});

it('CHN-03 lets reception resolve a channel problem, but not change channel settings', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    mappedType();
    importOta(otaReservation(code: 'SUITE'));
    $problem = ChannelException::query()->sole();
    $this->actingAs(staffUser(StaffRole::Receptionist));

    Livewire::test(ListChannelExceptions::class)
        ->assertCanSeeTableRecords([$problem])
        ->callTableAction('resolve', $problem, ['resolution' => 'Booked by hand as BL-XXXX']);

    expect($problem->fresh()->status)->toBe('resolved');

    Livewire::test(EditChannel::class, ['record' => bookingCom()->getRouteKey()])->assertForbidden();
});
