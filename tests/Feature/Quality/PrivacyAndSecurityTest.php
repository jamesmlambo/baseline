<?php

declare(strict_types=1);

require_once __DIR__.'/../Finance/FinanceTestHelpers.php';

use App\Actions\Bookings\CancelBooking;
use App\Enums\StaffRole;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Message;
use App\Models\Setting;
use App\Models\User;
use App\Services\Operations\ProductionReadiness;
use App\Services\Privacy\GuestPrivacy;
use App\Services\Privacy\RetentionReview;
use App\Services\Security\FileScanner;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function (): void {
    seedFinanceSettings();
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2027-06-01 10:00', 'Africa/Johannesburg'));
});

function finishedGuest(): Guest
{
    $booking = reserve(email: 'thandi@example.test');
    app(CancelBooking::class)->handle($booking, 'Changed plans', staffUser(StaffRole::Manager));

    return $booking->fresh()->guest;
}

function setSetting(string $key, mixed $value): void
{
    Setting::query()->updateOrCreate(['key' => $key], ['group' => 'test', 'label' => $key, 'type' => 'string', 'value' => $value]);
    Cache::flush();
}

// --- POPIA tools (PRIV-01, PRIV-03) ------------------------------------------------------

it('PRIV-03 exports what we hold about a guest and logs the export', function (): void {
    $guest = finishedGuest();

    $data = app(GuestPrivacy::class)->export($guest, staffUser(StaffRole::Manager));

    expect($data['profile']['email'])->toBe('thandi@example.test')
        ->and($data['bookings'])->toHaveCount(1)
        ->and(Activity::query()->where('event', 'guest_data_exported')->count())->toBe(1)
        ->and(fn () => app(GuestPrivacy::class)->export($guest, staffUser(StaffRole::Receptionist)))->toThrow(AuthorizationException::class);
});

it('PRIV-03 anonymises a guest everywhere operations use their details, but keeps invoices', function (): void {
    $guest = finishedGuest();
    $booking = Booking::query()->where('guest_id', $guest->id)->sole();
    $invoiceName = DB::table('invoices')->where('booking_id', $booking->id)->value('recipient_name');

    app(GuestPrivacy::class)->anonymise($guest, 'Guest asked to be forgotten', staffUser(StaffRole::Owner));

    $guest->refresh();
    expect($guest->email)->toBeNull()
        ->and($guest->email_hash)->toBeNull()
        ->and($guest->first_name)->toBe('Anonymised')
        ->and($booking->fresh()->lead_guest_name)->toBe(GuestPrivacy::PLACEHOLDER)
        ->and($booking->fresh()->lead_guest_email)->toBeNull()
        ->and(Message::query()->where('booking_id', $booking->id)->pluck('body')->unique()->all())->toBeIn([[], ['[removed]']])
        ->and(DB::table('invoices')->where('booking_id', $booking->id)->value('recipient_name'))->toBe($invoiceName)
        ->and(Guest::query()->matchingContact('thandi@example.test', null)->exists())->toBeFalse();
});

it('PRIV-03 refuses to anonymise on legal hold, with an open booking, or for anyone but the owner', function (): void {
    $guest = finishedGuest();
    $openGuest = reserve(email: 'open@example.test')->guest;

    expect(fn () => app(GuestPrivacy::class)->anonymise($guest, 'x', staffUser(StaffRole::Manager)))->toThrow(AuthorizationException::class)
        ->and(fn () => app(GuestPrivacy::class)->anonymise($openGuest, 'x', staffUser(StaffRole::Owner)))->toThrow(InvalidArgumentException::class);

    app(GuestPrivacy::class)->placeHold($guest, 'Dispute', staffUser(StaffRole::Manager));

    expect(fn () => app(GuestPrivacy::class)->anonymise($guest->fresh(), 'x', staffUser(StaffRole::Owner)))->toThrow(InvalidArgumentException::class, 'legal hold');
});

it('PRIV-03 changes nothing until retention periods are approved and switched on', function (): void {
    $guest = finishedGuest();
    $this->travelTo(CarbonImmutable::parse('2030-01-01'));

    expect(app(RetentionReview::class)->review()['guest_profiles']['affected'])->toBe(0);

    setSetting('retention.guest_profile_months', 12);
    expect(app(RetentionReview::class)->review()['guest_profiles']['affected'])->toBe(1)
        ->and(app(RetentionReview::class)->enforce())->toBe([])
        ->and($guest->fresh()->anonymised_at)->toBeNull();

    setSetting('retention.enforcement_enabled', 'yes');
    expect(app(RetentionReview::class)->enforce()['guest_profiles'])->toBe(1)
        ->and($guest->fresh()->anonymised_at)->not->toBeNull();
});

// --- Security (SEC-02, SEC-08) ----------------------------------------------------------

it('SEC-02 refuses a housekeeping worker on a payment screen and logs the attempt', function (): void {
    $worker = staffUser(StaffRole::HousekeepingWorker);

    $this->actingAs($worker)->get('/admin/payments')->assertForbidden();

    $log = Activity::query()->where('event', 'access_denied')->sole();
    expect($log->causer_id)->toBe($worker->id)
        ->and($log->properties['path'])->toBe('/admin/payments')
        ->and($log->properties)->not->toHaveKey('ip');
});

it('SEC-04 sends security headers on every page', function (): void {
    $this->get('/')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('SEC-08 marks uploads as not scanned while no scanner is connected, and removes infected files', function (): void {
    $type = financeRoomType();
    $path = tempnam(sys_get_temp_dir(), 'img').'.png';
    imagepng(imagecreatetruecolor(4, 4), $path);

    $media = $type->addMedia($path)->preservingOriginal()->toMediaCollection('gallery');
    expect($media->fresh()->getCustomProperty('virus_scan'))->toBe(FileScanner::NOT_SCANNED);

    app()->instance(FileScanner::class, new class implements FileScanner
    {
        public function scan(string $path): string
        {
            return self::INFECTED;
        }
    });
    $infected = $type->addMedia($path)->toMediaCollection('gallery');

    expect(Media::query()->find($infected->id))->toBeNull()
        ->and(Activity::query()->where('event', 'upload_infected')->count())->toBe(1);
})->skip(! function_exists('imagecreatetruecolor'), 'GD not available');

// --- Go-live readiness (acceptance 10) --------------------------------------------------

it('ACC-10 blocks go-live while demo data, demo accounts or fake drivers are active', function (): void {
    financeRoomType()->update(['is_demo' => true]);
    User::factory()->create(['email' => 'owner@baseline-lodge.test', 'is_active' => true]);

    $results = collect(app(ProductionReadiness::class)->check())->keyBy('area');

    expect($results['Demo data: accommodation_types']['status'])->toBe('fail')
        ->and($results['Demo accounts']['status'])->toBe('fail')
        ->and($results['Payments']['status'])->toBe('fail')
        ->and($results['Pending decisions']['status'])->toBe('warn');
});
