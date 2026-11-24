<?php

declare(strict_types=1);

require_once __DIR__.'/../Finance/FinanceTestHelpers.php';
require_once __DIR__.'/../Bookings/BookingTestHelpers.php';

use App\Actions\Messaging\CreatePriceList;
use App\Actions\Messaging\RunMessageAutomations;
use App\Enums\BookingSource;
use App\Enums\StaffRole;
use App\Filament\Resources\MessageTemplates\Pages\EditMessageTemplate;
use App\Filament\Resources\PriceLists\Pages\CreatePriceListPage;
use App\Filament\Resources\PriceLists\Pages\ListPriceLists;
use App\Models\Booking;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\PriceList;
use App\Models\RoomNight;
use Carbon\CarbonImmutable;
use Database\Seeders\MessageTemplateSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function (): void {
    seedFinanceSettings();
    $this->seed(MessageTemplateSeeder::class);
    Mail::fake();
    RateLimiter::clear('enquiries');
    $this->travelTo(CarbonImmutable::parse('2027-06-01 10:00', 'Africa/Johannesburg'));
});

function priceListFor(?string $phone = '082 777 0001'): PriceList
{
    return app(CreatePriceList::class)->handle(stay('2027-06-10', '2027-06-12'), 2, 0, staffUser(StaffRole::Receptionist), 'Thabo', $phone);
}

it('WA-01 creates an expiring price list and sends the link by WhatsApp', function (): void {
    $priceList = priceListFor();

    $message = Message::query()->where('idempotency_key', 'price_list:'.$priceList->id)->sole();

    expect($priceList->token)->toHaveLength(48)
        ->and($priceList->expires_at->toDateTimeString())->toBe(now()->addHours(24)->toDateTimeString())
        ->and($message->body)->toContain($priceList->url())
        ->and($message->body)->toContain('Hi Thabo');
});

it('WA-01/03 shows live prices without holding any room', function (): void {
    financeRoomType();
    $priceList = priceListFor();

    $this->get($priceList->url())->assertOk()->assertSee('Garden Room')->assertSee('R 2 000.00')->assertSee('Choose this room');

    expect(RoomNight::query()->count())->toBe(0)
        ->and($priceList->fresh()->open_count)->toBe(1);
});

it('WA-03 shows when the dates have filled up since the link was sent', function (): void {
    $type = financeRoomType();
    $priceList = priceListFor();
    bookType($type, stay('2027-06-10', '2027-06-12'));

    $this->get($priceList->url())->assertOk()->assertSee('those dates have just filled up');
});

it('stops working once expired or cancelled', function (string $how): void {
    $priceList = priceListFor();
    $how === 'expired' ? $this->travelTo(now()->addHours(25)) : $priceList->update(['revoked_at' => now()]);

    $this->get($priceList->url())->assertStatus(410)->assertSee('This price list has expired');
})->with(['expired', 'cancelled']);

it('WA-02 records a booking made from the price list as a WhatsApp booking', function (): void {
    setBankDetails();
    $type = financeRoomType();
    $priceList = priceListFor();

    $this->get(route('book', ['type' => $type->id, 'arrival' => '2027-06-10', 'departure' => '2027-06-12', 'adults' => 2, 'price_list' => $priceList->token]))
        ->assertOk()
        ->assertSee($priceList->token);

    $this->post('/book', [
        'accommodation_type_id' => $type->id, 'arrival' => '2027-06-10', 'departure' => '2027-06-12', 'adults' => 2, 'children' => 0,
        'first_name' => 'Thabo', 'last_name' => 'Demo', 'email' => 'thabo@example.test', 'phone' => '082 777 0001',
        'payment_method' => 'eft', 'accept_terms' => '1', 'price_list' => $priceList->token,
    ])->assertRedirect();

    $booking = Booking::query()->sole();

    expect($booking->source)->toBe(BookingSource::WhatsApp)
        ->and($priceList->fresh()->booking_id)->toBe($booking->id);
});

it('WA-02 offers only bank transfer to guests who book from a WhatsApp price list', function (): void {
    setBankDetails();
    $type = financeRoomType();
    $priceList = priceListFor();
    $form = [
        'accommodation_type_id' => $type->id, 'arrival' => '2027-06-10', 'departure' => '2027-06-12', 'adults' => 2, 'children' => 0,
        'first_name' => 'Thabo', 'last_name' => 'Demo', 'email' => 'thabo@example.test', 'phone' => '082 777 0001',
        'accept_terms' => '1', 'price_list' => $priceList->token,
    ];

    $this->get(route('book', ['type' => $type->id, 'arrival' => '2027-06-10', 'departure' => '2027-06-12', 'adults' => 2, 'price_list' => $priceList->token]))
        ->assertOk()
        ->assertSee('value="eft"', false)
        ->assertDontSee('value="instant_eft"', false)
        ->assertDontSee('value="card"', false);

    // Choosing a card or Instant EFT anyway is refused and books nothing.
    $this->post('/book', $form + ['payment_method' => 'card'])->assertSessionHasErrors('payment_method');
    expect(Booking::query()->count())->toBe(0);

    $this->post('/book', $form + ['payment_method' => 'eft'])->assertRedirect();
    $booking = Booking::query()->sole();

    $this->get(route('booking.show', $booking->public_token))
        ->assertOk()
        ->assertSee('Pay by EFT (bank transfer)')
        ->assertDontSee('Pay with Instant EFT')
        ->assertDontSee('Pay with Card');

    $this->post(route('booking.pay', $booking->public_token), ['method' => 'card'])->assertRedirect(route('booking.show', $booking->public_token));
    expect($booking->payments()->count())->toBe(0);
});

it('WA-02 still lets ordinary website guests pay by card or Instant EFT', function (): void {
    setBankDetails();
    $type = financeRoomType();

    $this->get(route('book', ['type' => $type->id, 'arrival' => '2027-06-10', 'departure' => '2027-06-12', 'adults' => 2]))
        ->assertOk()
        ->assertSee('value="instant_eft"', false)
        ->assertSee('value="card"', false)
        ->assertSee('value="eft"', false);
});

it('ignores an expired price-list token when booking', function (): void {
    $type = financeRoomType();
    $priceList = priceListFor();
    $priceList->update(['revoked_at' => now()]);

    $this->post('/book', [
        'accommodation_type_id' => $type->id, 'arrival' => '2027-06-10', 'departure' => '2027-06-12', 'adults' => 2,
        'first_name' => 'T', 'last_name' => 'D', 'email' => 't@example.test', 'phone' => '082 777 0001',
        'payment_method' => 'instant_eft', 'accept_terms' => '1', 'price_list' => $priceList->token,
    ]);

    expect(Booking::query()->sole()->source)->toBe(BookingSource::Website)
        ->and($priceList->fresh()->booking_id)->toBeNull();
});

it('MSG-03 follows up an unused price list once, after the set time', function (): void {
    $priceList = priceListFor();

    app(RunMessageAutomations::class)->handle();
    expect($priceList->fresh()->followed_up_at)->toBeNull();

    $this->travelTo(now()->addHours(5));
    app(RunMessageAutomations::class)->handle();
    app(RunMessageAutomations::class)->handle();

    expect($priceList->fresh()->followed_up_at)->not->toBeNull()
        ->and(Message::query()->where('idempotency_key', 'price_list_follow_up:'.$priceList->id)->count())->toBe(1);
});

it('builds a WhatsApp click-to-chat link for reception to send themselves', function (): void {
    $url = CreatePriceList::clickToChatUrl(priceListFor());

    expect($url)->toStartWith('https://wa.me/27827770001?text=')
        ->and(rawurldecode($url))->toContain('/p/');
});

it('does not let field staff create price lists', function (): void {
    app(CreatePriceList::class)->handle(stay('2027-06-10', '2027-06-12'), 2, 0, staffUser(StaffRole::HousekeepingWorker));
})->throws(AuthorizationException::class);

it('creates a price list from the admin screen', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staffUser(StaffRole::Receptionist));

    Livewire::test(CreatePriceListPage::class)
        ->fillForm(['arrival' => '2027-06-10', 'departure' => '2027-06-12', 'adults' => 2, 'customer_name' => 'Thabo', 'customer_phone' => '082 777 0001', 'send' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $priceList = PriceList::query()->sole();
    Livewire::test(ListPriceLists::class)->assertCanSeeTableRecords([$priceList]);
});

it('rejects message wording with an unknown placeholder', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staffUser(StaffRole::Manager));
    $template = MessageTemplate::query()->where('key', 'farewell')->firstOrFail();

    Livewire::test(EditMessageTemplate::class, ['record' => $template->getRouteKey()])
        ->fillForm(['body' => 'Bye {{ guest_nmae }}'])
        ->call('save')
        ->assertHasErrors(['data.body']);

    expect($template->fresh()->version)->toBe(1);
    expect(staffUser(StaffRole::Receptionist)->can('update', $template))->toBeFalse();
});
