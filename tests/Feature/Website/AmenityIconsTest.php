<?php

declare(strict_types=1);

use App\Enums\AmenityCategory;
use App\Enums\StaffRole;
use App\Filament\Resources\AccommodationTypes\Pages\EditAccommodationType;
use App\Models\AccommodationType;
use App\Models\Amenity;
use Database\Seeders\AmenitySeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('WEB-05 adds the client\'s 45 room amenities in their order, each with its own icon', function (): void {
    $this->seed(AmenitySeeder::class);

    $amenities = Amenity::query()->orderBy('sort_order')->get();

    expect($amenities)->toHaveCount(45)
        ->and($amenities->first()->name)->toBe('Shower')
        ->and($amenities->last()->name)->toBe('Pet-friendly')
        ->and($amenities->firstWhere('name', 'Wi-Fi')->icon)->toBe('phosphor-wifi-high-duotone')
        ->and($amenities->every(fn (Amenity $amenity): bool => $amenity->category === AmenityCategory::Room && ! $amenity->is_demo))->toBeTrue();

    // Every icon exists in the installed icon pack (none falls back to the check mark).
    $fallback = Amenity::svgFor(Amenity::FALLBACK_ICON)->toHtml();
    foreach ($amenities as $amenity) {
        expect($amenity->iconSvg()->toHtml())->not->toBe($fallback, "{$amenity->name} has no icon");
    }
});

it('WEB-05 keeps staff changes when the amenities are loaded again, and turns a DEMO duplicate into the real one', function (): void {
    Amenity::query()->create(['name' => 'Wi-Fi', 'category' => AmenityCategory::Room, 'icon' => 'heroicon-o-wifi', 'description' => 'DEMO — confirm details.', 'is_demo' => true]);
    $this->seed(AmenitySeeder::class);
    Amenity::query()->where('name', 'Kettle')->update(['icon' => 'phosphor-coffee-duotone', 'is_published' => false]);

    $this->seed(AmenitySeeder::class);

    expect(Amenity::query()->count())->toBe(45)
        ->and(Amenity::query()->where('name', 'Wi-Fi')->sole()->only(['icon', 'is_demo', 'description']))->toBe(['icon' => 'phosphor-wifi-high-duotone', 'is_demo' => false, 'description' => null])
        ->and(Amenity::query()->where('name', 'Kettle')->sole()->only(['icon', 'is_published']))->toBe(['icon' => 'phosphor-coffee-duotone', 'is_published' => false]);
});

it('WEB-05 shows each ticked amenity with its icon before the name on the room page', function (): void {
    $this->seed(AmenitySeeder::class);
    $type = AccommodationType::factory()->create(['name' => 'Garden Room', 'slug' => 'garden-room']);
    $type->amenities()->attach(Amenity::query()->whereIn('name', ['Bathtub', 'King bed'])->pluck('id'));

    $html = $this->get('/rooms/garden-room')->assertOk()->getContent();

    expect($html)->toMatch('/<svg[^>]*aria-hidden="true"[^>]*>.*?<\/svg>\s*Bathtub/s')
        ->toMatch('/<svg[^>]*>.*?<\/svg>\s*King bed/s');
});

it('WEB-05 shows the amenities with icons as tick boxes on the room type form', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staffUser(StaffRole::Owner));
    $this->seed(AmenitySeeder::class);
    $type = AccommodationType::factory()->create();

    Livewire::test(EditAccommodationType::class, ['record' => $type->getRouteKey()])
        ->assertOk()
        ->assertSee('Wheelchair-accessible room')
        ->assertSeeHtml('<span>Hairdryer</span>')
        ->fillForm(['amenities' => Amenity::query()->whereIn('name', ['Shower', 'Safe'])->pluck('id')->all()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($type->amenities()->orderBy('sort_order')->pluck('name')->all())->toBe(['Shower', 'Safe']);
});

it('WEB-07 saves room photos uploaded in the admin to the public disk, so the website can show them', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staffUser(StaffRole::Owner));
    $type = AccommodationType::factory()->create(['slug' => 'garden-room']);

    Livewire::test(EditAccommodationType::class, ['record' => $type->getRouteKey()])
        ->fillForm(['gallery' => [UploadedFile::fake()->image('room.jpg', 40, 30)]])
        ->call('save')
        ->assertHasNoFormErrors();

    $media = $type->fresh()->getMedia('gallery')->sole();

    expect($media->disk)->toBe('public')
        ->and(Storage::disk('public')->exists($media->getPathRelativeToRoot()))->toBeTrue();
})->skip(! function_exists('imagecreatetruecolor'), 'GD not available');
