<?php

declare(strict_types=1);

require_once __DIR__.'/../Finance/FinanceTestHelpers.php';

use App\Actions\Website\RecordEnquiry;
use App\Enums\EnquiryType;
use App\Http\Middleware\CaptureCampaign;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

it('REP-06 remembers the campaign a visitor arrived from, first touch only', function (): void {
    $this->get('/?utm_source=facebook&utm_medium=social&utm_campaign=winter-special')
        ->assertSessionHas(CaptureCampaign::SESSION_KEY, ['source' => 'facebook', 'medium' => 'social', 'campaign' => 'winter-special', 'landing' => '/']);

    $this->get('/?utm_source=google&utm_campaign=other')
        ->assertSessionHas(CaptureCampaign::SESSION_KEY.'.campaign', 'winter-special');
});

it('REP-06 keeps only the referring site\'s host name, and strips odd characters', function (): void {
    $this->withHeader('referer', 'https://www.example-blog.test/some/long/path?with=secrets')
        ->get('/')
        ->assertSessionHas(CaptureCampaign::SESSION_KEY.'.source', 'www.example-blog.test');

    $this->flushSession();

    $this->get('/?utm_campaign=<script>alert(1)</script>')
        ->assertSessionHas(CaptureCampaign::SESSION_KEY.'.campaign', 'scriptalert1script');
});

it('REP-06 attaches the campaign to a website booking', function (): void {
    seedFinanceSettings();
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2027-06-01 10:00', 'Africa/Johannesburg'));
    $type = financeRoomType();

    $this->get('/?utm_source=instagram&utm_campaign=june-specials');
    $this->post('/book', [
        'accommodation_type_id' => $type->id,
        'arrival' => '2027-06-10',
        'departure' => '2027-06-12',
        'adults' => 2,
        'children' => 0,
        'first_name' => 'Lerato',
        'last_name' => 'Demo',
        'email' => 'lerato@example.test',
        'phone' => '082 555 0101',
        'payment_method' => 'instant_eft',
        'accept_terms' => '1',
    ])->assertRedirect();

    expect(Booking::query()->sole()->attribution)->toMatchArray(['source' => 'instagram', 'campaign' => 'june-specials']);
});

it('REP-06 attaches the campaign to a website enquiry', function (): void {
    $this->get('/?utm_source=newsletter&utm_campaign=june');

    $enquiry = app(RecordEnquiry::class)->handle(EnquiryType::Contact, ['name' => 'Ayanda', 'email' => 'a@example.test', 'message' => 'Hello'], '127.0.0.1');

    expect($enquiry->fresh()->attribution)->toMatchArray(['source' => 'newsletter', 'campaign' => 'june']);
});
