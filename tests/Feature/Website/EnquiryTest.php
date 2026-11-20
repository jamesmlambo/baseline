<?php

declare(strict_types=1);

require_once __DIR__.'/../Bookings/BookingTestHelpers.php';

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Models\AccommodationType;
use App\Models\Enquiry;
use App\Models\RoomNight;
use App\Models\Setting;
use App\Notifications\NewWebsiteEnquiry;
use Database\Seeders\SettingSeeder;
use Database\Seeders\WebsiteContentSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    $this->seed([SettingSeeder::class, WebsiteContentSeeder::class]);
    RateLimiter::clear('enquiries');
});

function contactForm(array $overrides = []): array
{
    return [
        'name' => 'Thandi Demo',
        'email' => 'thandi@example.test',
        'phone' => '',
        'message' => 'Do you allow pets?',
        ...$overrides,
    ];
}

it('WEB-10 stores a contact message and confirms it', function (): void {
    $this->post('/contact-us', contactForm())
        ->assertRedirect('/contact-us')
        ->assertSessionHas('status');

    $enquiry = Enquiry::query()->sole();

    expect($enquiry->type)->toBe(EnquiryType::Contact)
        ->and($enquiry->status)->toBe(EnquiryStatus::New)
        ->and($enquiry->marketing_consent)->toBeFalse()
        ->and($enquiry->ip_hash)->not->toBe('127.0.0.1')->toHaveLength(64);
});

it('needs an email address or a phone number', function (): void {
    $this->post('/contact-us', contactForm(['email' => '', 'phone' => '']))
        ->assertSessionHasErrors(['email', 'phone']);

    $this->post('/contact-us', contactForm(['email' => '', 'phone' => '082 123 4567']))
        ->assertSessionHasNoErrors();
});

it('rejects submissions that fill the hidden spam field', function (): void {
    $this->post('/contact-us', contactForm(['website' => 'http://spam.example']))
        ->assertSessionHasErrors('website');

    expect(Enquiry::query()->count())->toBe(0);
});

it('limits each visitor to five enquiries a minute', function (): void {
    foreach (range(1, 5) as $i) {
        $this->post('/contact-us', contactForm())->assertRedirect();
    }

    $this->post('/contact-us', contactForm())->assertTooManyRequests();
});

it('OBK-03 records marketing consent only when ticked', function (): void {
    $this->post('/contact-us', contactForm(['marketing_consent' => '1']));

    expect(Enquiry::query()->sole()->marketing_consent)->toBeTrue();
});

it('emails the enquiry inbox a link, not the guest details, when an inbox is set', function (): void {
    Notification::fake();
    Setting::query()->where('key', 'website.enquiry_email')->first()->update(['value' => 'frontdesk@baseline-lodge.test']);

    $this->post('/contact-us', contactForm());

    Notification::assertSentOnDemand(NewWebsiteEnquiry::class, function (NewWebsiteEnquiry $notification, array $channels, object $notifiable): bool {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes['mail'] === 'frontdesk@baseline-lodge.test'
            && ! str_contains(implode(' ', $mail->introLines), 'Thandi');
    });
});

it('shows live availability and prices for a search', function (): void {
    $type = AccommodationType::factory()->withRooms(2)->create(['name' => 'Garden Room', 'base_nightly_rate_cents' => 100_000]);

    $this->get('/check-availability?arrival=2027-05-01&departure=2027-05-03&adults=2')
        ->assertOk()
        ->assertSee('Garden Room')
        ->assertSee('R 2 000.00')
        ->assertSee('2 rooms left')
        ->assertSee(route('book', ['type' => $type->id, 'arrival' => '2027-05-01', 'departure' => '2027-05-03', 'adults' => 2, 'children' => 0]));
});

it('leaves fully booked room types out of the results', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create(['name' => 'Garden Room']);
    bookType($type, stay('2027-05-01', '2027-05-03'));

    $this->get('/check-availability?arrival=2027-05-02&departure=2027-05-04&adults=2')
        ->assertSee('nothing is free for those dates');
});

it('refuses searches in the past', function (): void {
    $this->get('/check-availability?arrival=2020-01-01&departure=2020-01-03')
        ->assertSessionHasErrors('arrival');
});

it('turns a booking request into an enquiry that holds no room', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create(['base_nightly_rate_cents' => 90_000]);

    $this->get(route('book', ['type' => $type->id, 'arrival' => '2027-05-01', 'departure' => '2027-05-03', 'adults' => 2]))
        ->assertOk()
        ->assertSee('Your room is not reserved yet')
        ->assertSee('R 1 800.00');

    $this->post('/book', [
        'accommodation_type_id' => $type->id,
        'arrival' => '2027-05-01',
        'departure' => '2027-05-03',
        'adults' => 2,
        'children' => 0,
        'name' => 'Sipho Demo',
        'phone' => '082 000 0000',
        'accept_terms' => '1',
    ])->assertRedirect('/')->assertSessionHas('status');

    $enquiry = Enquiry::query()->sole();

    expect($enquiry->type)->toBe(EnquiryType::BookingRequest)
        ->and($enquiry->quoted_total_cents->cents)->toBe(180_000)
        ->and($enquiry->arrival_date->toDateString())->toBe('2027-05-01')
        ->and(RoomNight::query()->count())->toBe(0);
});

it('requires guests to accept the booking policies', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create();

    $this->post('/book', [
        'accommodation_type_id' => $type->id,
        'arrival' => '2027-05-01',
        'departure' => '2027-05-03',
        'adults' => 2,
        'name' => 'Sipho Demo',
        'phone' => '082 000 0000',
    ])->assertSessionHasErrors('accept_terms');
});

it('refuses a party too big for the room', function (): void {
    $type = AccommodationType::factory()->withRooms(1)->create(['max_occupancy' => 2]);

    $this->from('/book')->post('/book', [
        'accommodation_type_id' => $type->id,
        'arrival' => '2027-05-01',
        'departure' => '2027-05-03',
        'adults' => 4,
        'name' => 'Big Party',
        'phone' => '082 000 0000',
        'accept_terms' => '1',
    ])->assertSessionHasErrors('adults');

    expect(Enquiry::query()->count())->toBe(0);
});
