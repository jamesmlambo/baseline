<?php

declare(strict_types=1);

require_once __DIR__.'/PortalTestHelpers.php';

use App\Actions\Portal\EscalateGuestRequests;
use App\Actions\Portal\ModerateReview;
use App\Actions\Reception\AddCharge;
use App\Enums\BookingStatus;
use App\Enums\GuestRequestStatus;
use App\Enums\GuestRequestType;
use App\Enums\ReviewStatus;
use App\Enums\StaffRole;
use App\Models\GuestMessage;
use App\Models\GuestRequest;
use App\Models\MenuItem;
use App\Models\Review;
use App\Notifications\GuestRequestEscalated;
use App\Services\Portal\GuestRequests;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    seedFinanceSettings();
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2027-06-10 14:00', 'Africa/Johannesburg'));
});

function menuItem(int $priceCents = 9_500, bool $available = true): MenuItem
{
    return MenuItem::query()->create([
        'category' => 'Breakfast',
        'name' => 'Test breakfast '.MenuItem::query()->count(),
        'price_cents' => $priceCents,
        'is_available' => $available,
        'is_demo' => true,
    ]);
}

// --- Requests and messages (GST-07) -----------------------------------------------------

it('GST-07 sends a towel request to reception', function (): void {
    $booking = portalStay();
    signInToPortal(portalRoom($booking));

    $this->post(route('portal.requests.store'), ['type' => 'towels', 'details' => 'Two more please'])->assertRedirect(route('portal.requests'));

    $request = GuestRequest::query()->sole();
    expect($request->booking_id)->toBe($booking->id)
        ->and($request->type)->toBe(GuestRequestType::Towels)
        ->and($request->status)->toBe(GuestRequestStatus::New)
        ->and($request->room_id)->toBe(portalRoom($booking)->id);
});

it('GST-07 does not let guests create meal or checkout requests through the simple form', function (): void {
    $booking = portalStay();
    signInToPortal(portalRoom($booking));

    $this->post(route('portal.requests.store'), ['type' => 'meal'])->assertSessionHasErrors('type');

    expect(GuestRequest::query()->count())->toBe(0);
});

it('GST-07 prices a meal order from the menu, not from the form', function (): void {
    $booking = portalStay();
    signInToPortal(portalRoom($booking));
    $item = menuItem(9_500);

    $this->post(route('portal.menu.order'), ['quantities' => [$item->id => 2], 'charge_to_room' => '1', 'total_cents' => 1])
        ->assertRedirect(route('portal.requests'));

    expect(GuestRequest::query()->sole()->total_cents->cents)->toBe(19_000);
});

it('GST-07 refuses items that are switched off', function (): void {
    $booking = portalStay();
    signInToPortal(portalRoom($booking));
    $item = menuItem(available: false);

    $this->post(route('portal.menu.order'), ['quantities' => [$item->id => 1]])->assertSessionHasErrors('quantities');

    expect(GuestRequest::query()->count())->toBe(0);
});

it('GST-07 adds a room-service order to the bill once, when reception marks it done', function (): void {
    $booking = portalStay();
    $order = app(GuestRequests::class)->orderMeal($booking, [menuItem(9_500)->id => 2], null, chargeToRoom: true);
    $receptionist = staffUser(StaffRole::Receptionist);

    expect($booking->fresh()->balanceDue()->cents)->toBe(0);

    app(GuestRequests::class)->complete($order, $receptionist);
    app(GuestRequests::class)->complete($order->fresh(), $receptionist);

    expect($booking->fresh()->balanceDue()->cents)->toBe(19_000)
        ->and($order->fresh()->status)->toBe(GuestRequestStatus::Done)
        ->and($order->fresh()->invoice_id)->not->toBeNull();
});

it('GST-07 does not bill an order the guest will pay for on delivery', function (): void {
    $booking = portalStay();
    $order = app(GuestRequests::class)->orderMeal($booking, [menuItem()->id => 1], null, chargeToRoom: false);

    app(GuestRequests::class)->complete($order, staffUser(StaffRole::Receptionist));

    expect($booking->fresh()->balanceDue()->cents)->toBe(0);
});

it('GST-07 does not let housekeeping complete guest requests', function (): void {
    $booking = portalStay();
    $request = app(GuestRequests::class)->submit($booking, GuestRequestType::Towels, null);

    expect(fn () => app(GuestRequests::class)->complete($request, staffUser(StaffRole::HousekeepingWorker)))
        ->toThrow(AuthorizationException::class);
});

it('GST-07 shows reception replies to the guest and marks their message answered', function (): void {
    $booking = portalStay();
    signInToPortal(portalRoom($booking));

    $this->post(route('portal.messages.send'), ['body' => 'Is the pool open?'])->assertRedirect(route('portal.messages'));
    app(GuestRequests::class)->reply($booking, 'Yes, until 18:00.', staffUser(StaffRole::Receptionist));

    $this->get(route('portal.messages'))->assertOk()->assertSee('Is the pool open?')->assertSee('Yes, until 18:00.');
    expect(GuestMessage::query()->where('direction', GuestMessage::IN)->sole()->read_at)->not->toBeNull();
});

// --- Checkout from the portal (CHK-01, CHK-02) ------------------------------------------

it('CHK-01 lets a guest who owes nothing check out from the portal', function (): void {
    $booking = portalStay();
    signInToPortal(portalRoom($booking));

    $this->post(route('portal.checkout'))->assertRedirect(route('portal.start'));

    expect($booking->fresh()->status)->toBe(BookingStatus::CheckedOut)
        ->and($booking->fresh()->checked_out_by)->toBeNull();
    $this->get(route('portal.home'))->assertRedirect(route('portal.start'));
});

it('CHK-02 sends a guest who owes money to reception instead of checking them out', function (): void {
    $booking = portalStay();
    app(AddCharge::class)->handle($booking, 'Minibar', 1, Money::cents(5_000), staffUser(StaffRole::Receptionist));
    signInToPortal(portalRoom($booking));

    $this->post(route('portal.checkout'))->assertRedirect(route('portal.bill'));
    $this->post(route('portal.checkout'));

    expect($booking->fresh()->status)->toBe(BookingStatus::CheckedIn)
        ->and(GuestRequest::query()->where('type', GuestRequestType::Checkout)->count())->toBe(1);
});

// --- Reviews (GST-07) -------------------------------------------------------------------

it('GST-07 takes one review per stay and holds it for moderation', function (): void {
    $booking = portalStay();
    signInToPortal(portalRoom($booking));

    $this->post(route('portal.review.store'), ['rating' => 5, 'comment' => 'Lovely stay', 'consent_to_publish' => '1'])->assertRedirect(route('portal.review'));
    $this->post(route('portal.review.store'), ['rating' => 1, 'comment' => 'Second try']);

    $review = Review::query()->sole();
    expect($review->rating)->toBe(5)->and($review->status)->toBe(ReviewStatus::Pending);
    $this->get(route('home'))->assertDontSee('Lovely stay');
});

it('GST-07 shows a review on the homepage only after a publisher approves it', function (): void {
    $booking = portalStay();
    $review = Review::query()->create(['booking_id' => $booking->id, 'rating' => 4, 'comment' => 'Quiet and clean', 'consent_to_publish' => true, 'status' => ReviewStatus::Pending]);

    app(ModerateReview::class)->handle($review, ReviewStatus::Approved, staffUser(StaffRole::Manager));

    $this->get(route('home'))->assertOk()->assertSee('Quiet and clean');
});

it('GST-07 never publishes a review without the guest\'s consent', function (): void {
    $booking = portalStay();
    $review = Review::query()->create(['booking_id' => $booking->id, 'rating' => 4, 'consent_to_publish' => false, 'status' => ReviewStatus::Pending]);

    expect(fn () => app(ModerateReview::class)->handle($review, ReviewStatus::Approved, staffUser(StaffRole::Manager)))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => DB::table('reviews')->where('id', $review->id)->update(['status' => 'approved']))
        ->toThrow(QueryException::class);
});

it('GST-07 does not let reception moderate reviews', function (): void {
    $booking = portalStay();
    $review = Review::query()->create(['booking_id' => $booking->id, 'rating' => 4, 'consent_to_publish' => true, 'status' => ReviewStatus::Pending]);

    expect(fn () => app(ModerateReview::class)->handle($review, ReviewStatus::Approved, staffUser(StaffRole::Receptionist)))
        ->toThrow(AuthorizationException::class);
});

// --- Escalation (GST-08) ----------------------------------------------------------------

it('GST-08 escalates an untouched request to managers, then to the owner', function (): void {
    Notification::fake();
    $booking = portalStay();
    $manager = staffUser(StaffRole::Manager);
    $owner = staffUser(StaffRole::Owner);
    app(GuestRequests::class)->submit($booking, GuestRequestType::Towels, null);

    $this->travel(11)->minutes();
    app(EscalateGuestRequests::class)->handle();
    app(EscalateGuestRequests::class)->handle();

    Notification::assertSentToTimes($manager, GuestRequestEscalated::class, 1);
    Notification::assertNotSentTo($owner, GuestRequestEscalated::class);

    $this->travel(20)->minutes();
    app(EscalateGuestRequests::class)->handle();

    Notification::assertSentToTimes($owner, GuestRequestEscalated::class, 1);
});

it('GST-08 does not escalate a request reception has picked up', function (): void {
    Notification::fake();
    $booking = portalStay();
    $manager = staffUser(StaffRole::Manager);
    $request = app(GuestRequests::class)->submit($booking, GuestRequestType::Towels, null);
    app(GuestRequests::class)->acknowledge($request, staffUser(StaffRole::Receptionist));

    $this->travel(40)->minutes();
    app(EscalateGuestRequests::class)->handle();

    Notification::assertNotSentTo($manager, GuestRequestEscalated::class);
});
