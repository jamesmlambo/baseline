<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Enums\TaskType;
use App\Models\AccommodationType;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Laravel Cloud stores files in buckets (S3-style), not on the server disk, which is wiped on every deploy.
 */
function useCloudStyleDisk(string $name): void
{
    config(["filesystems.disks.{$name}" => ['driver' => 's3', 'key' => 'x', 'secret' => 'x', 'region' => 'auto', 'bucket' => $name]]);
    Storage::fake($name);
}

function jpegWithExif(string $marker): string
{
    $path = tempnam(sys_get_temp_dir(), 'exif').'.jpg';
    imagejpeg(imagecreatetruecolor(40, 30), $path);
    $payload = "Exif\0\0II*\0\x08\0\0\0\0\0".$marker;
    $jpeg = (string) file_get_contents($path);
    file_put_contents($path, substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2));

    return $path;
}

it('SEC-08 still removes location data from photos stored in cloud buckets', function (): void {
    useCloudStyleDisk('public');
    Exceptions::fake();
    $type = AccommodationType::factory()->create();

    $media = $type->addMedia(jpegWithExif('GPS-SECRET-LOCATION'))->toMediaCollection('gallery');
    Exceptions::assertNothingReported();
    $stored = (string) Storage::disk('public')->get($media->getPathRelativeToRoot());

    expect($stored)->not->toContain('GPS-SECRET-LOCATION')
        ->and($media->fresh()->size)->toBe(strlen($stored));
})->skip(! function_exists('imagecreatetruecolor'), 'GD not available');

it('PRIV-01 keeps private photos on the default disk, which is the private bucket on Laravel Cloud', function (): void {
    useCloudStyleDisk('private-bucket');
    config(['filesystems.default' => 'private-bucket']);
    $task = Task::query()->create(['type' => TaskType::Repairs, 'title' => 'Fix tap', 'status' => 'open', 'priority' => 'normal', 'source' => 'manual']);

    $media = $task->addMedia(jpegWithExif('x'))->toMediaCollection('photos');

    expect($media->disk)->toBe('private-bucket')
        ->and(Storage::disk('private-bucket')->exists($media->getPathRelativeToRoot()))->toBeTrue();
})->skip(! function_exists('imagecreatetruecolor'), 'GD not available');

it('creates the first Owner with a one-time password, and never overwrites an existing user', function (): void {
    $this->artisan('app:create-owner', ['email' => 'Owner@Example.test', 'name' => 'Lodge Owner'])
        ->expectsOutputToContain('Owner owner@example.test created.')
        ->expectsOutputToContain('One-time password:')
        ->assertSuccessful();

    $owner = User::query()->where('email', 'owner@example.test')->sole();

    expect($owner->hasRole(StaffRole::Owner->value))->toBeTrue()
        ->and($owner->is_active)->toBeTrue()
        ->and(Hash::needsRehash($owner->password))->toBeFalse();

    $this->artisan('app:create-owner', ['email' => 'owner@example.test', 'name' => 'Someone Else'])->assertFailed();

    expect(User::query()->where('email', 'owner@example.test')->sole()->name)->toBe('Lodge Owner');
});
