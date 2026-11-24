<?php

declare(strict_types=1);

use App\Enums\AmenityCategory;
use App\Models\AccommodationType;
use App\Models\Amenity;
use App\Services\Website\CloudContentCopy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The "Cloud" side is an empty copy of all tables in a separate schema of the test database.
 */
beforeEach(function (): void {
    // Its own connection (outside the test's transaction), so the schema and tables are really there.
    config(['database.connections.copy_target' => array_merge(config('database.connections.pgsql'), ['search_path' => 'copy_target'])]);
    DB::purge('copy_target');
    DB::connection('copy_target')->statement('DROP SCHEMA IF EXISTS copy_target CASCADE');
    DB::connection('copy_target')->statement('CREATE SCHEMA copy_target');
    Artisan::call('migrate', ['--database' => 'copy_target', '--force' => true]);

    Storage::fake('public');
    Storage::fake('cloud_public');
});

afterEach(function (): void {
    DB::connection('copy_target')->statement('DROP SCHEMA IF EXISTS copy_target CASCADE');
    DB::connection('copy_target')->disconnect();
});

function contentCopy(): CloudContentCopy
{
    return new CloudContentCopy(DB::connection(), DB::connection('copy_target'), Storage::disk('public'), Storage::disk('cloud_public'));
}

function laptopRoomType(): AccommodationType
{
    $type = AccommodationType::factory()->create(['name' => 'Garden Room', 'slug' => 'garden-room', 'is_demo' => false, 'base_nightly_rate_cents' => 95_000, 'description' => 'Quiet, with a garden view.']);
    $type->rooms()->create(['name' => 'Room 1', 'code' => 'G1', 'sort_order' => 1, 'is_active' => true, 'is_demo' => false]);
    $amenity = Amenity::query()->create(['name' => 'Bird feeder', 'category' => AmenityCategory::Room, 'icon' => 'phosphor-bird-duotone', 'is_published' => true]);
    $type->amenities()->attach($amenity);

    // A photo on the public disk with one website size, as the media library stores them.
    $mediaId = DB::table('media')->insertGetId([
        'model_type' => AccommodationType::class, 'model_id' => $type->id, 'uuid' => (string) str()->uuid(),
        'collection_name' => 'gallery', 'name' => 'garden', 'file_name' => 'garden.jpg', 'mime_type' => 'image/jpeg',
        'disk' => 'public', 'conversions_disk' => 'public', 'size' => 4, 'manipulations' => '[]', 'custom_properties' => '[]',
        'generated_conversions' => '{"card":true}', 'responsive_images' => '[]', 'order_column' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    Storage::disk('public')->put("{$mediaId}/garden.jpg", 'JPEG');
    Storage::disk('public')->put("{$mediaId}/conversions/garden-card.webp", 'WEBP');

    return $type;
}

it('WEB-08 shows what would be copied in a dry run and changes nothing', function (): void {
    laptopRoomType();
    AccommodationType::factory()->create(['slug' => 'demo-room', 'is_demo' => true]);

    $result = contentCopy()->run(apply: false);

    expect($result['report'])->toContain('+ room type Garden Room (garden-room)', '    + room Room 1 (G1)', '+ amenity Bird feeder', '    + photo garden.jpg')
        ->and(implode("\n", $result['report']))->not->toContain('demo-room')
        ->and(DB::connection('copy_target')->table('accommodation_types')->count())->toBe(0)
        ->and(Storage::disk('cloud_public')->allFiles())->toBe([]);
});

it('WEB-08 copies room types, rooms, amenities and photos, and copies nothing twice', function (): void {
    $type = laptopRoomType();
    DB::connection('copy_target')->table('amenities')->insert(['name' => 'Wi-Fi', 'category' => 'room', 'sort_order' => 1, 'is_published' => true, 'is_demo' => false, 'created_at' => now(), 'updated_at' => now()]);

    $first = contentCopy()->run(apply: true);
    $target = DB::connection('copy_target');
    $cloudType = $target->table('accommodation_types')->where('slug', 'garden-room')->first();
    $cloudPhoto = $target->table('media')->where('model_id', $cloudType->id)->first();

    expect($first['counts'])->toBe(['types' => 1, 'rooms' => 1, 'amenities' => 1, 'photos' => 1])
        ->and((int) $cloudType->base_nightly_rate_cents)->toBe(95_000)
        ->and($cloudType->description)->toBe('Quiet, with a garden view.')
        ->and($target->table('rooms')->where('code', 'G1')->value('accommodation_type_id'))->toBe($cloudType->id)
        ->and($target->table('accommodation_type_amenity')->where('accommodation_type_id', $cloudType->id)->count())->toBe(1)
        ->and(Storage::disk('cloud_public')->get("{$cloudPhoto->id}/garden.jpg"))->toBe('JPEG')
        ->and(Storage::disk('cloud_public')->get("{$cloudPhoto->id}/conversions/garden-card.webp"))->toBe('WEBP')
        ->and($target->table('amenities')->where('name', 'Wi-Fi')->exists())->toBeTrue();

    // A later price change on the laptop is the only thing copied next time.
    $type->update(['base_nightly_rate_cents' => 110_000]);
    $second = contentCopy()->run(apply: true);

    expect($second['report'])->toBe(['~ room type Garden Room (garden-room): base_nightly_rate_cents'])
        ->and((int) $target->table('accommodation_types')->where('slug', 'garden-room')->value('base_nightly_rate_cents'))->toBe(110_000)
        ->and($target->table('media')->count())->toBe(1);
});

it('WEB-08 refuses to copy from anywhere but the laptop', function (): void {
    app()['env'] = 'staging';

    $this->artisan('app:copy-content-to-cloud')->expectsOutputToContain('local environment')->assertFailed();
});
