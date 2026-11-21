<?php

declare(strict_types=1);

require_once __DIR__.'/../Finance/FinanceTestHelpers.php';

use App\Actions\Bookings\CancelBooking;
use App\Actions\Inventory\BlockRoom;
use App\Enums\PaymentMethod;
use App\Enums\RoomBlockReason;
use App\Enums\StaffRole;
use App\Filament\Pages\OwnerDashboard;
use App\Filament\Resources\DailyReports\Pages\ViewDailyReport;
use App\Mail\DailyReportMail;
use App\Models\Booking;
use App\Models\DailyReport;
use App\Models\ReportDelivery;
use App\Models\Room;
use App\Services\Payments\PaymentLedger;
use App\Services\Reports\DailyReports;
use App\Services\Reports\ReportMetrics;
use App\Support\StayDates;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    seedFinanceSettings();
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2027-06-10 12:00', 'Africa/Johannesburg'));
});

function day(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date, 'Africa/Johannesburg');
}

/**
 * Two nights (10–12 June) at R1 000, paid in cash today.
 */
function paidStay(string $email = 'guest@example.test'): Booking
{
    $booking = reserve(email: $email);
    app(PaymentLedger::class)->recordAtReception($booking, PaymentMethod::Cash, $booking->deposit_due_cents, staffUser(StaffRole::Receptionist));

    return $booking->fresh();
}

// --- Metrics (REP-01, REP-02) -----------------------------------------------------------

it('REP-01 works out occupancy, ADR and RevPAR on a stay-date basis', function (): void {
    paidStay();
    $rooms = Room::query()->active()->count();

    $metrics = app(ReportMetrics::class)->forPeriod(day('2027-06-10'), day('2027-06-11'))['rooms'];

    expect($metrics['rooms_available'])->toBe($rooms * 2)
        ->and($metrics['nights_sold'])->toBe(2)
        ->and($metrics['room_revenue_cents'])->toBe(200_000)
        ->and($metrics['adr_cents'])->toBe(100_000)
        ->and($metrics['occupancy_percent'])->toBe(round(2 / ($rooms * 2) * 100, 1))
        ->and($metrics['revpar_cents'])->toBe((int) round(200_000 / ($rooms * 2)));
});

it('REP-02 keeps pending, cancelled and confirmed stays apart and only counts confirmed as sold', function (): void {
    paidStay();
    reserve(email: 'held@example.test', arrival: '2027-06-10', departure: '2027-06-11');
    $cancelled = reserve(email: 'cancel@example.test', arrival: '2027-06-10', departure: '2027-06-11');
    app(CancelBooking::class)->handle($cancelled, 'Changed plans', staffUser(StaffRole::Manager));

    $report = app(ReportMetrics::class)->forPeriod(day('2027-06-10'), day('2027-06-10'));

    expect($report['stays'])->toMatchArray(['confirmed' => 1, 'pending' => 1, 'cancelled' => 1])
        ->and($report['rooms']['nights_sold'])->toBe(1);
});

it('REP-01 takes blocked rooms out of availability', function (): void {
    $room = Room::query()->active()->first() ?? Room::factory()->create();
    $before = app(ReportMetrics::class)->forPeriod(day('2027-06-20'), day('2027-06-20'))['rooms']['rooms_available'];

    app(BlockRoom::class)->handle($room, new StayDates('2027-06-20', '2027-06-21'), RoomBlockReason::Maintenance, 'Painting', staffUser(StaffRole::Manager));

    $after = app(ReportMetrics::class)->forPeriod(day('2027-06-20'), day('2027-06-20'))['rooms'];
    expect($after['rooms_available'])->toBe($before - 1)->and($after['nights_blocked'])->toBe(1);
});

it('REP-02 counts payments on the day the money arrived', function (): void {
    paidStay();

    expect(app(ReportMetrics::class)->forPeriod(day('2027-06-10'), day('2027-06-10'))['money']['payments_received_cents'])->toBe(200_000)
        ->and(app(ReportMetrics::class)->forPeriod(day('2027-06-11'), day('2027-06-11'))['money']['payments_received_cents'])->toBe(0)
        ->and(app(ReportMetrics::class)->forPeriod(day('2027-06-10'), day('2027-06-10'))['money']['payments_by_method_cents'])->toBe(['cash' => 200_000]);
});

it('REP-06 measures online conversion and campaigns by the date the booking was made', function (): void {
    $confirmed = paidStay();
    $confirmed->forceFill(['attribution' => ['source' => 'facebook', 'campaign' => 'winter']])->save();
    reserve(email: 'abandoned@example.test', arrival: '2027-06-15', departure: '2027-06-16')
        ->forceFill(['attribution' => ['source' => 'facebook', 'campaign' => 'winter']])->save();

    $marketing = app(ReportMetrics::class)->forPeriod(day('2027-06-10'), day('2027-06-10'))['marketing'];

    expect($marketing['online_bookings_started'])->toBe(2)
        ->and($marketing['online_bookings_confirmed'])->toBe(1)
        ->and($marketing['online_conversion_percent'])->toBe(50.0)
        ->and($marketing['campaigns'])->toBe(['winter' => ['made' => 2, 'confirmed' => 1]]);
});

// --- The 17:00 report (REP-03) ----------------------------------------------------------

it('REP-03 does nothing before the report time', function (): void {
    staffUser(StaffRole::Owner);

    expect(app(DailyReports::class)->run(day('2027-06-10 16:59')))->toBeNull()
        ->and(DailyReport::query()->count())->toBe(0);
});

it('REP-03 stores the snapshot once and emails every owner, with push logged as not connected', function (): void {
    $owner = staffUser(StaffRole::Owner);
    staffUser(StaffRole::Manager);
    paidStay();

    $report = app(DailyReports::class)->run(day('2027-06-10 17:00'));
    app(DailyReports::class)->run(day('2027-06-10 17:05'));

    expect(DailyReport::query()->count())->toBe(1)
        ->and($report->data['money']['payments_received_cents'])->toBe(200_000)
        ->and(ReportDelivery::query()->where('channel', 'email')->sole()->status)->toBe('sent')
        ->and(ReportDelivery::query()->where('channel', 'push')->sole()->status)->toBe('skipped');
    Mail::assertSent(DailyReportMail::class, 1);
    Mail::assertSent(DailyReportMail::class, fn (DailyReportMail $mail): bool => $mail->hasTo($owner->email));
});

it('REP-03 keeps guest details out of the email', function (): void {
    staffUser(StaffRole::Owner);
    $booking = paidStay();
    $report = app(DailyReports::class)->generate(day('2027-06-10'));

    $html = (new DailyReportMail($report, 'https://example.test/admin/daily-reports/1'))->render();

    expect($html)->not->toContain($booking->lead_guest_name)
        ->not->toContain('guest@example.test')
        ->toContain('Occupancy');
});

it('REP-03 retries a failed email with a growing gap, up to five tries', function (): void {
    $owner = staffUser(StaffRole::Owner);
    $report = app(DailyReports::class)->generate(day('2027-06-10'));
    $delivery = ReportDelivery::query()->create(['daily_report_id' => $report->id, 'user_id' => $owner->id, 'channel' => 'email', 'status' => 'pending']);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

    app(DailyReports::class)->attempt($delivery);
    expect($delivery->fresh()->status)->toBe('failed')->and($delivery->fresh()->last_error)->toBe('SMTP down');

    expect(app(DailyReports::class)->retryFailed(CarbonImmutable::now()->addMinutes(2)))->toBe(0)
        ->and(app(DailyReports::class)->retryFailed(CarbonImmutable::now()->addMinutes(6)))->toBe(1);

    $delivery->forceFill(['attempts' => ReportDelivery::MAX_ATTEMPTS])->save();
    expect(app(DailyReports::class)->retryFailed(CarbonImmutable::now()->addDay()))->toBe(0);
});

it('REP-05 schedules guest escalations separately from the daily report', function (): void {
    $commands = collect(app(Schedule::class)->events())->map(fn ($event): string => (string) $event->command)->implode("\n");

    expect($commands)->toContain('portal:escalate')->toContain('reports:daily');
});

// --- Archive and day close (REP-04) -----------------------------------------------------

it('REP-04 lets a manager close a day once, recording the money totals', function (): void {
    $this->travelTo(day('2027-06-10 18:00'));
    paidStay();
    $report = app(DailyReports::class)->generate(day('2027-06-10'));

    app(DailyReports::class)->freeze($report, staffUser(StaffRole::Manager));

    expect($report->fresh()->frozen_totals['payments_received_cents'])->toBe(200_000)
        ->and(fn () => app(DailyReports::class)->freeze($report->fresh(), staffUser(StaffRole::Owner)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(DailyReports::class)->freeze(app(DailyReports::class)->generate(day('2027-06-09')), staffUser(StaffRole::Receptionist)))->toThrow(AuthorizationException::class);
});

it('REP-04 does not close a day before its evening', function (): void {
    $report = app(DailyReports::class)->generate(day('2027-06-10'));

    expect(fn () => app(DailyReports::class)->freeze($report, staffUser(StaffRole::Owner)))->toThrow(InvalidArgumentException::class);
});

// --- Screens ----------------------------------------------------------------------------

it('REP-01 shows the owner dashboard to the owner and keeps reception out', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    paidStay();

    $this->actingAs(staffUser(StaffRole::Owner));
    Livewire::test(OwnerDashboard::class)
        ->assertOk()
        ->assertSee('Occupancy')
        ->assertSee('by stay date')
        ->assertSee('by transaction date')
        ->set('preset', 'month')
        ->assertSee('R 2 000.00');

    $this->actingAs(staffUser(StaffRole::Receptionist));
    Livewire::test(OwnerDashboard::class)->assertForbidden();
});

it('REP-04 shows a stored report and its deliveries', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $owner = staffUser(StaffRole::Owner);
    $report = app(DailyReports::class)->run(day('2027-06-10 17:30'));
    $this->actingAs($owner);

    Livewire::test(ViewDailyReport::class, ['record' => $report->getKey()])
        ->assertOk()
        ->assertSee('Deliveries')
        ->assertSee('Skipped');
});
