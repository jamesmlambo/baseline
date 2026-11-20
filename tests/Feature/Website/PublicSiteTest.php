<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\AccommodationType;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Database\Seeders\WebsiteContentSeeder;

beforeEach(function (): void {
    $this->seed([SettingSeeder::class, WebsiteContentSeeder::class]);
});

function publishedPost(array $attributes = []): Post
{
    return Post::query()->create([
        'type' => PostType::News,
        'status' => PostStatus::Published,
        'title' => 'Pool reopens',
        'slug' => 'pool-reopens',
        'excerpt' => 'The pool is open again.',
        'body' => '<p>Come for a swim.</p>',
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);
}

it('serves every public page from the spec', function (string $path): void {
    AccommodationType::factory()->withRooms(1)->create(['slug' => 'garden-room', 'name' => 'Garden Room']);
    publishedPost();

    $this->get($path)->assertOk()->assertSee('Baseline Lodge')->assertDontSee('Nyala');
})->with([
    'home' => '/',
    'about' => '/about-us',
    'rooms' => '/rooms',
    'room details' => '/rooms/garden-room',
    'features' => '/features-and-amenities',
    'services' => '/services',
    'pool' => '/swimming-pool-and-day-passes',
    'braai' => '/braai-facilities',
    'vip' => '/vip-areas',
    'spa' => '/spa-and-massage',
    'news' => '/news',
    'news item' => '/news/pool-reopens',
    'faqs' => '/faqs',
    'contact' => '/contact-us',
    'availability' => '/check-availability',
    'guest portal' => '/guest-portal',
    'house rules' => '/house-rules',
    'policies' => '/booking-and-cancellation-policies',
    'privacy' => '/privacy-policy',
    'communication policy' => '/official-communication-policy',
]);

it('WEB-08 marks placeholder pages as DEMO and never invents contact details', function (): void {
    $this->get('/about-us')
        ->assertSee('DEMO — replace before launch')
        ->assertSee('Contact details coming soon');

    expect(Setting::get('contact.phone'))->toBeNull();
});

it('shows contact details and a WhatsApp link once they are set', function (): void {
    Setting::query()->where('key', 'contact.phone')->first()->update(['value' => '012 345 6789']);
    Setting::query()->where('key', 'contact.whatsapp')->first()->update(['value' => '082 123 4567']);

    $this->get('/')
        ->assertSee('012 345 6789')
        ->assertSee('https://wa.me/27821234567', false);
});

it('WEB-06 shows room prices from the rate engine', function (): void {
    AccommodationType::factory()->withRooms(1)->create([
        'slug' => 'garden-room',
        'base_nightly_rate_cents' => 123_450,
    ]);

    $this->get('/rooms')->assertSee('R 1 234.50');
    $this->get('/rooms/garden-room')->assertSee('R 1 234.50');
});

it('hides inactive room types', function (): void {
    AccommodationType::factory()->create(['slug' => 'hidden-room', 'is_active' => false]);

    $this->get('/rooms/hidden-room')->assertNotFound();
});

it('hides unpublished pages', function (): void {
    Page::query()->where('key', 'about')->update(['is_published' => false]);

    $this->get('/about-us')->assertNotFound();
});

it('CMS-02 only shows published posts whose publish date has passed', function (PostStatus $status, string $publishedAt, bool $visible): void {
    publishedPost(['status' => $status, 'published_at' => $status === PostStatus::Draft ? null : $publishedAt]);

    $response = $this->get('/news/pool-reopens');

    $visible ? $response->assertOk() : $response->assertNotFound();
})->with([
    'published' => [PostStatus::Published, '-1 day', true],
    'scheduled' => [PostStatus::Published, '+1 day', false],
    'draft' => [PostStatus::Draft, '-1 day', false],
    'in review' => [PostStatus::InReview, '-1 day', false],
    'archived' => [PostStatus::Archived, '-1 day', false],
]);

it('CMS-04 lists the latest three published posts on the homepage', function (): void {
    foreach (range(1, 4) as $i) {
        publishedPost(['title' => "Story {$i}", 'slug' => "story-{$i}", 'published_at' => now()->subDays(5 - $i)]);
    }

    $this->get('/')->assertSee('Story 4')->assertSee('Story 2')->assertDontSee('Story 1');
});

it('strips scripts from CMS content before showing it', function (): void {
    publishedPost(['body' => '<p>Hello</p><script>alert("x")</script><a href="javascript:alert(1)">bad</a>']);

    $this->get('/news/pool-reopens')
        ->assertSee('Hello')
        ->assertDontSee('<script>alert', false)
        ->assertDontSee('javascript:alert', false);
});

it('WEB-04 publishes a sitemap of public URLs only', function (): void {
    publishedPost();
    publishedPost(['slug' => 'secret-draft', 'title' => 'Draft', 'status' => PostStatus::Draft, 'published_at' => null]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(route('page', 'about-us'), false)
        ->assertSee(route('news.show', 'pool-reopens'), false)
        ->assertDontSee('secret-draft', false);
});

it('WEB-04 adds structured data for the lodge and its rooms', function (): void {
    AccommodationType::factory()->create(['slug' => 'garden-room']);

    $this->get('/')->assertSee('"@type":"LodgingBusiness"', false);
    $this->get('/rooms/garden-room')->assertSee('"@type":"HotelRoom"', false);
});

it('keeps non-production sites out of search engines', function (): void {
    $this->get('/')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('WEB-07 only shows grading stars once evidence is verified', function (): void {
    Setting::query()->where('key', 'website.grading_stars')->first()->update(['value' => 4]);

    $this->get('/')->assertDontSee('4-star graded');

    Setting::query()->where('key', 'website.grading_verified')->first()->update(['value' => 'yes']);

    $this->get('/')->assertSee('4-star graded');
});

it('shows a friendly page for unknown addresses', function (): void {
    $this->get('/no-such-page')->assertNotFound()->assertSee("We can't find that page", false);
});
