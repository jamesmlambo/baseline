<?php

declare(strict_types=1);

use App\Actions\Website\PostWorkflow;
use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\StaffRole;
use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Enquiry;
use App\Models\Page;
use App\Models\Post;
use Database\Seeders\WebsiteContentSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(WebsiteContentSeeder::class);
});

function draftPost(): Post
{
    return Post::query()->create([
        'type' => PostType::News,
        'status' => PostStatus::Draft,
        'title' => 'Draft story',
        'slug' => 'draft-story',
        'body' => '<p>Text</p>',
    ]);
}

it('CMS-02 lets a content editor submit a draft but not publish it', function (): void {
    $editor = staffUser(StaffRole::ContentEditor);
    $post = draftPost();
    $workflow = app(PostWorkflow::class);

    $workflow->submitForReview($post, $editor);
    expect($post->fresh()->status)->toBe(PostStatus::InReview);

    expect(fn () => $workflow->publish($post, $editor))->toThrow(AuthorizationException::class);
});

it('CMS-02 lets a manager publish, and records who did', function (): void {
    $manager = staffUser(StaffRole::Manager);
    $post = draftPost();

    app(PostWorkflow::class)->publish($post, $manager);

    expect($post->fresh())
        ->status->toBe(PostStatus::Published)
        ->published_by->toBe($manager->id)
        ->published_at->not->toBeNull();
});

it('stops content editors changing a post once it is in review', function (): void {
    $editor = staffUser(StaffRole::ContentEditor);
    $post = draftPost();
    app(PostWorkflow::class)->submitForReview($post, $editor);

    expect($editor->can('update', $post->fresh()))->toBeFalse()
        ->and(staffUser(StaffRole::Owner)->can('update', $post->fresh()))->toBeTrue();
});

it('creates new posts as drafts whoever writes them', function (): void {
    $this->actingAs(staffUser(StaffRole::Owner));

    Livewire::test(CreatePost::class)
        ->fillForm([
            'type' => 'news',
            'title' => 'Spring specials',
            'slug' => 'spring-specials',
            'body' => '<p>Soon.</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Post::query()->where('slug', 'spring-specials')->sole()->status)->toBe(PostStatus::Draft);
});

it('publishes from the edit screen button', function (): void {
    $this->actingAs(staffUser(StaffRole::Manager));
    $post = draftPost();

    Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
        ->callAction('publish')
        ->assertNotified('Published');

    expect($post->fresh()->status)->toBe(PostStatus::Published);
});

it('lets content editors edit pages but keeps reception out of the website screens', function (): void {
    $page = Page::query()->where('key', 'about')->sole();

    expect(staffUser(StaffRole::ContentEditor)->can('update', $page))->toBeTrue()
        ->and(staffUser(StaffRole::Receptionist)->can('viewAny', Page::class))->toBeFalse();
});

it('never allows built-in pages to be deleted', function (): void {
    $owner = staffUser(StaffRole::Owner);
    $builtIn = Page::query()->where('key', 'about')->sole();
    $custom = Page::query()->create(['slug' => 'weddings', 'title' => 'Weddings']);

    expect($owner->can('delete', $builtIn))->toBeFalse()
        ->and($owner->can('delete', $custom))->toBeTrue();
});

it('edits a page from the admin screen', function (): void {
    $this->actingAs(staffUser(StaffRole::ContentEditor));
    $page = Page::query()->where('key', 'about')->sole();

    Livewire::test(ListPages::class)->assertCanSeeTableRecords([$page]);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['title' => 'Our story', 'is_demo' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->fresh())->title->toBe('Our story')->is_demo->toBeFalse();
    $this->get('/about-us')->assertSee('Our story');
});

it('refuses page addresses that clash with built-in routes', function (): void {
    $this->actingAs(staffUser(StaffRole::Owner));
    $page = Page::query()->where('key', 'about')->sole();

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['slug' => 'rooms'])
        ->call('save')
        ->assertHasFormErrors(['slug']);
});

it('lets reception work through website enquiries', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $enquiry = Enquiry::query()->create([
        'type' => EnquiryType::Contact,
        'status' => EnquiryStatus::New,
        'name' => 'Demo Visitor',
        'email' => 'visitor@example.test',
    ]);

    Livewire::test(ListEnquiries::class)
        ->assertCanSeeTableRecords([$enquiry])
        ->callTableAction('changeStatus', $enquiry, ['status' => 'closed'])
        ->assertNotified('Enquiry updated');

    expect($enquiry->fresh()->status)->toBe(EnquiryStatus::Closed)
        ->and($enquiry->fresh()->handled_by)->toBe(auth()->id());
});

it('keeps enquiries away from field staff and content editors', function (): void {
    expect(staffUser(StaffRole::HousekeepingWorker)->can('viewAny', Enquiry::class))->toBeFalse()
        ->and(staffUser(StaffRole::ContentEditor)->can('viewAny', Enquiry::class))->toBeFalse();
});
