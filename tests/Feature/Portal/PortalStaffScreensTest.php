<?php

declare(strict_types=1);

require_once __DIR__.'/PortalTestHelpers.php';

use App\Enums\GuestRequestStatus;
use App\Enums\GuestRequestType;
use App\Enums\ReviewStatus;
use App\Enums\StaffRole;
use App\Filament\Resources\AccommodationTypes\Pages\EditAccommodationType;
use App\Filament\Resources\AccommodationTypes\RelationManagers\RoomsRelationManager;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\GuestMessages\Pages\ListGuestMessages;
use App\Filament\Resources\GuestRequests\Pages\ListGuestRequests;
use App\Filament\Resources\MenuItems\Pages\ListMenuItems;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Livewire\GuestPortalAlert;
use App\Models\GuestMessage;
use App\Models\Review;
use App\Services\Portal\GuestRequests;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    seedFinanceSettings();
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2027-06-10 14:00', 'Africa/Johannesburg'));
});

it('GST-08 lists new guest requests for reception and marks them in progress', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $request = app(GuestRequests::class)->submit(portalStay(), GuestRequestType::Towels, 'Two more please');

    Livewire::test(ListGuestRequests::class)
        ->assertCanSeeTableRecords([$request])
        ->callTableAction('acknowledge', $request)
        ->assertNotified();

    expect($request->fresh()->status)->toBe(GuestRequestStatus::Acknowledged);
});

it('GST-08 lets reception reply to a guest message', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $booking = portalStay();
    $message = app(GuestRequests::class)->messageReception($booking, 'Is the pool open?');

    Livewire::test(ListGuestMessages::class)
        ->assertCanSeeTableRecords([$message])
        ->callTableAction('reply', $message, ['body' => 'Yes, until 18:00.'])
        ->assertNotified('Reply sent to the guest portal');

    expect($booking->guestMessages()->where('direction', GuestMessage::OUT)->sole()->body)->toBe('Yes, until 18:00.');
});

it('GST-08 chimes when a new guest request arrives', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $booking = portalStay();
    $alert = Livewire::test(GuestPortalAlert::class);

    app(GuestRequests::class)->submit($booking, GuestRequestType::Towels, null);

    $alert->call('check')->assertDispatched('guest-portal-chime');
    $alert->call('check')->assertNotDispatched('guest-portal-chime');
});

it('GST-07 lets a manager add a menu item', function (): void {
    $this->actingAs(staffUser(StaffRole::Manager));

    Livewire::test(ListMenuItems::class)
        ->callAction('create', ['name' => 'Toast', 'category' => 'Breakfast', 'price_cents' => '45.00', 'is_available' => true])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('menu_items', ['name' => 'Toast', 'price_cents' => 4_500]);
});

it('GST-07 lets a manager publish a review with consent', function (): void {
    $this->actingAs(staffUser(StaffRole::Manager));
    $review = Review::query()->create(['booking_id' => portalStay()->id, 'rating' => 5, 'consent_to_publish' => true, 'status' => ReviewStatus::Pending]);

    Livewire::test(ListReviews::class)
        ->callTableAction('publish', $review)
        ->assertNotified('Review published');

    expect($review->fresh()->status)->toBe(ReviewStatus::Approved);
});

it('GST-03 issues a portal link from the booking screen after an ID check', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $booking = portalStay();

    Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
        ->callAction('portalLink', ['identity_check' => 'Checked passport'])
        ->assertNotified('Portal link ready (30 minutes)')
        ->assertActionMounted('showPortalLink');
});

it('GST-06 ends portal access from the booking screen', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $booking = portalStay();

    Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
        ->callAction('endPortalAccess', ['reason' => 'Lost phone'])
        ->assertNotified('Portal access ended');

    expect($booking->fresh()->portal_revoked_at)->not->toBeNull();
});

it('GST-01 shows and replaces a room QR code', function (): void {
    $this->actingAs(staffUser(StaffRole::Manager));
    $room = portalRoom(portalStay());
    $oldToken = $room->ensurePortalToken();

    Livewire::test(RoomsRelationManager::class, ['ownerRecord' => $room->accommodationType, 'pageClass' => EditAccommodationType::class])
        ->assertTableActionVisible('portalQr', $room)
        ->callTableAction('replacePortalQr', $room)
        ->assertNotified();

    expect($room->fresh()->portal_token)->not->toBe($oldToken);
});
