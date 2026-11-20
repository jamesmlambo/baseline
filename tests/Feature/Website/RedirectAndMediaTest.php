<?php

declare(strict_types=1);

use App\Models\AccommodationType;
use App\Models\Redirect;
use Illuminate\Support\Facades\Storage;

it('WEB-05 sends old addresses to their new home and counts the hits', function (): void {
    $redirect = Redirect::query()->create(['from_path' => 'https://old.example/Rooms.HTML/', 'to_path' => '/rooms']);

    expect($redirect->from_path)->toBe('/rooms.html');

    $this->get('/rooms.html')->assertRedirect('/rooms')->assertStatus(301);
    $this->get('/ROOMS.html/')->assertRedirect('/rooms');

    expect($redirect->fresh()->hits)->toBe(2)
        ->and($redirect->fresh()->last_hit_at)->not->toBeNull();
});

it('supports temporary redirects and ignores inactive ones', function (): void {
    Redirect::query()->create(['from_path' => '/specials', 'to_path' => '/news', 'status_code' => 302]);
    Redirect::query()->create(['from_path' => '/gone', 'to_path' => '/', 'is_active' => false]);

    $this->get('/specials')->assertStatus(302);
    $this->get('/gone')->assertNotFound();
});

it('SEC-08 strips camera and location data from uploaded photos', function (): void {
    Storage::fake('public');
    $type = AccommodationType::factory()->create();

    $path = tempnam(sys_get_temp_dir(), 'exif').'.jpg';
    $image = imagecreatetruecolor(40, 30);
    imagejpeg($image, $path);

    // Insert an APP1/EXIF segment carrying a marker string straight after the JPEG start marker.
    $marker = 'GPS-SECRET-LOCATION';
    $payload = "Exif\0\0II*\0\x08\0\0\0\0\0".$marker;
    $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    $jpeg = file_get_contents($path);
    file_put_contents($path, substr($jpeg, 0, 2).$segment.substr($jpeg, 2));

    expect(file_get_contents($path))->toContain($marker);

    $media = $type->addMedia($path)->toMediaCollection('gallery');

    expect(file_get_contents($media->getPath()))->not->toContain($marker);
});
