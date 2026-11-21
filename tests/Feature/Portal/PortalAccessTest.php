<?php

declare(strict_types=1);

require_once __DIR__.'/PortalTestHelpers.php';

use App\Actions\Bookings\ReassignRoom;
use App\Actions\Reception\CheckOut;
use App\Enums\GuestRequestType;
use App\Enums\StaffRole;
use App\Models\Message;
use App\Models\PortalCode;
use App\Models\Room;
use App\Services\Portal\GuestRequests;
use App\Services\Portal\PortalAccess;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    seedFinanceSettings();
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2027-06-10 14:00', 'Africa/Johannesburg'));
});

// --- Sign-in with the room QR and a code (GST-01, GST-02, GST-03) ------------------------

it('GST-02 signs the booker in with the code sent to the booking phone', function (): void {
    $booking = portalStay();

    signInToPortal(portalRoom($booking))->assertRedirect(route('portal.home'));

    $this->get(route('portal.home'))->assertOk()->assertSee('Lerato');
});

it('GST-02 gives the same reply and sends nothing for a number that is not on the stay', function (): void {
    $booking = portalStay();
    $sent = captureSms();
    $token = portalRoom($booking)->ensurePortalToken();

    $this->post(route('portal.room.code', $token), ['phone' => '083 000 0000'])
        ->assertRedirect(route('portal.room.verify', $token));

    expect($sent)->toHaveCount(0)->and(PortalCode::query()->count())->toBe(0);
});

it('GST-02 gives the same reply and sends nothing for an empty room', function (): void {
    $room = Room::factory()->create();
    $sent = captureSms();
    $token = $room->ensurePortalToken();

    $this->post(route('portal.room.code', $token), ['phone' => PORTAL_BOOKER_PHONE])
        ->assertRedirect(route('portal.room.verify', $token));

    expect($sent)->toHaveCount(0);
});

it('GST-02 rejects an expired code', function (): void {
    $booking = portalStay();
    $sent = captureSms();
    $token = portalRoom($booking)->ensurePortalToken();
    $this->post(route('portal.room.code', $token), ['phone' => PORTAL_BOOKER_PHONE]);

    $this->travel(PortalAccess::CODE_TTL_MINUTES + 1)->minutes();

    $this->post(route('portal.room.verify.submit', $token), ['code' => lastPortalCode($sent)])->assertSessionHasErrors('code');
    $this->get(route('portal.home'))->assertRedirect(route('portal.start'));
});

it('GST-02 stops accepting a code after five wrong tries, even the right one', function (): void {
    $booking = portalStay();
    $sent = captureSms();
    $token = portalRoom($booking)->ensurePortalToken();
    $this->post(route('portal.room.code', $token), ['phone' => PORTAL_BOOKER_PHONE]);
    $code = lastPortalCode($sent);
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, PortalAccess::MAX_ATTEMPTS) as $attempt) {
        $this->post(route('portal.room.verify.submit', $token), ['code' => $wrong])->assertSessionHasErrors('code');
    }

    $this->post(route('portal.room.verify.submit', $token), ['code' => $code])->assertSessionHasErrors('code');
});

it('GST-02 sends at most three codes per stay in 15 minutes', function (): void {
    $booking = portalStay();
    $sent = captureSms();
    $room = portalRoom($booking);

    foreach (range(1, 4) as $send) {
        app(PortalAccess::class)->requestCode($room, PORTAL_BOOKER_PHONE, '10.0.0.'.$send);
    }

    expect($sent)->toHaveCount(PortalAccess::MAX_SENDS);

    $this->travel(PortalAccess::SEND_WINDOW_MINUTES + 1)->minutes();
    app(PortalAccess::class)->requestCode($room, PORTAL_BOOKER_PHONE, '10.0.0.9');

    expect($sent)->toHaveCount(PortalAccess::MAX_SENDS + 1);
});

it('GST-03 never stores the code where staff could read it', function (): void {
    $booking = portalStay();
    $sent = captureSms();

    app(PortalAccess::class)->requestCode(portalRoom($booking), PORTAL_BOOKER_PHONE, '10.0.0.1');
    $code = lastPortalCode($sent);

    $logged = Message::query()->where('idempotency_key', 'like', 'portal_code:%')->sole();

    expect($logged->body)->not->toContain($code)->toContain('••••••')
        ->and(PortalCode::query()->sole()->code_hash)->not->toContain($code);
});

it('GST-02 rate limits code requests from one device', function (): void {
    $token = Room::factory()->create()->ensurePortalToken();

    foreach (range(1, 3) as $request) {
        $this->post(route('portal.room.code', $token), ['phone' => PORTAL_BOOKER_PHONE])->assertRedirect();
    }

    $this->post(route('portal.room.code', $token), ['phone' => PORTAL_BOOKER_PHONE])->assertTooManyRequests();
});

// --- Session ends when the stay changes (GST-06) ----------------------------------------

it('GST-06 signs the guest out when the stay is checked out', function (): void {
    $booking = portalStay();
    signInToPortal(portalRoom($booking));

    app(CheckOut::class)->handle($booking->fresh(), staffUser(StaffRole::Receptionist));

    $this->get(route('portal.home'))->assertRedirect(route('portal.start'));
});

it('GST-06 signs the guest out when the room QR is replaced', function (): void {
    $booking = portalStay();
    $room = portalRoom($booking);
    signInToPortal($room);

    app(PortalAccess::class)->rotateRoomQr($room, staffUser(StaffRole::Manager));

    $this->get(route('portal.home'))->assertRedirect(route('portal.start'));
});

it('GST-06 signs the guest out when they move to another room', function (): void {
    $booking = portalStay();
    $room = portalRoom($booking);
    signInToPortal($room);
    $other = Room::factory()->create(['accommodation_type_id' => $room->accommodation_type_id]);

    app(ReassignRoom::class)->handle($booking->activeBookingRooms()->sole(), $other, 'Air conditioner broken', staffUser(StaffRole::Receptionist));

    $this->get(route('portal.home'))->assertRedirect(route('portal.start'));
});

it('GST-06 signs every device out when reception ends portal access, but allows a fresh sign-in', function (): void {
    $booking = portalStay();
    $room = portalRoom($booking);
    signInToPortal($room);

    $this->travel(1)->seconds();
    app(PortalAccess::class)->endAccess($booking, staffUser(StaffRole::Receptionist), 'Lost phone');

    $this->get(route('portal.home'))->assertRedirect(route('portal.start'));

    $this->travel(1)->seconds();
    signInToPortal($room)->assertRedirect(route('portal.home'));
});

it('GST-06 only lets a guest see their own stay', function (): void {
    $booking = portalStay();
    signInToPortal(portalRoom($booking));
    $otherStay = portalStay();
    app(GuestRequests::class)->submit($otherStay, GuestRequestType::Other, 'Other stay private note');
    app(GuestRequests::class)->submit($booking, GuestRequestType::Towels, 'My own towels');

    $this->get(route('portal.requests'))->assertOk()->assertSee('My own towels')->assertDontSee('Other stay private note');
});

// --- Signed links (GST-03, GST-05) ------------------------------------------------------

it('GST-05 opens the portal from the signed link in the welcome message', function (): void {
    $booking = portalStay();

    $this->get(app(PortalAccess::class)->linkFor($booking))->assertRedirect(route('portal.home'));
    $this->get(route('portal.home'))->assertOk();
});

it('GST-05 rejects a tampered link', function (): void {
    $booking = portalStay();
    $other = portalStay();
    $link = str_replace('/link/'.$booking->id, '/link/'.$other->id, app(PortalAccess::class)->linkFor($booking));

    $this->get($link)->assertForbidden();
});

it('GST-05 refuses a link once the stay has ended', function (): void {
    $booking = portalStay();
    $link = app(PortalAccess::class)->linkFor($booking);
    app(CheckOut::class)->handle($booking, staffUser(StaffRole::Receptionist));

    $this->get($link)->assertRedirect(route('portal.start'));
});

it('GST-03 lets reception issue a 30-minute link after an ID check and records it', function (): void {
    $booking = portalStay();
    $receptionist = staffUser(StaffRole::Receptionist);

    $link = app(PortalAccess::class)->receptionLink($booking, $receptionist, 'Checked passport');

    expect(Activity::query()->where('event', 'portal_recovery_link')->sole()->properties['identity_check'])->toBe('Checked passport');

    $this->travel(31)->minutes();
    $this->get($link)->assertForbidden();
});

it('GST-03 does not let housekeeping issue portal links', function (): void {
    $booking = portalStay();

    expect(fn () => app(PortalAccess::class)->receptionLink($booking, staffUser(StaffRole::HousekeepingWorker), 'n/a'))
        ->toThrow(AuthorizationException::class);
});

// --- Extra phones (GST-04) --------------------------------------------------------------

it('GST-04 lets the booker add a phone that can then sign in', function (): void {
    $booking = portalStay();
    $room = portalRoom($booking);
    signInToPortal($room);

    $this->post(route('portal.phones.add'), ['label' => 'Partner', 'phone' => '083 444 0202'])->assertRedirect(route('portal.phones'));

    $this->post(route('portal.sign-out'));
    signInToPortal($room, '083 444 0202')->assertRedirect(route('portal.home'));
});

it('GST-04 does not let an added guest add or remove phones', function (): void {
    $booking = portalStay();
    $room = portalRoom($booking);
    signInToPortal($room);
    $this->post(route('portal.phones.add'), ['phone' => '083 444 0202']);
    $this->post(route('portal.sign-out'));

    signInToPortal($room, '083 444 0202');

    $this->post(route('portal.phones.add'), ['phone' => '084 333 0303'])->assertForbidden();
    $this->post(route('portal.phones.remove', $booking->authorizedPhones()->sole()))->assertForbidden();
});

it('GST-04 signs a removed guest out', function (): void {
    $booking = portalStay();
    $room = portalRoom($booking);
    signInToPortal($room);
    $this->post(route('portal.phones.add'), ['phone' => '083 444 0202']);
    $phone = $booking->authorizedPhones()->sole();
    $this->post(route('portal.sign-out'));
    signInToPortal($room, '083 444 0202');

    $phone->forceFill(['revoked_at' => now()])->save();

    $this->get(route('portal.home'))->assertRedirect(route('portal.start'));
});
