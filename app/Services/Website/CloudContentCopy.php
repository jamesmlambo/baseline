<?php

declare(strict_types=1);

namespace App\Services\Website;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Copies website room content from one database to another (the laptop to the Laravel Cloud
 * staging site): amenities (by name), room types (by slug), their rooms (by code), ticked
 * amenities and gallery photos with their website sizes. Bookings, guests, payments and users
 * are never touched. Running it again only adds what is missing and updates what changed.
 */
final class CloudContentCopy
{
    private const TYPE_MODEL = 'App\\Models\\AccommodationType';

    private const TYPE_COLUMNS = ['name', 'slug', 'summary', 'description', 'base_occupancy', 'max_occupancy', 'base_nightly_rate_cents', 'extra_guest_nightly_cents', 'sort_order', 'is_active', 'is_demo'];

    private const AMENITY_COLUMNS = ['category', 'icon', 'description', 'sort_order', 'is_published', 'is_demo'];

    /** @var list<string> */
    private array $report = [];

    /** @var array{types: int, rooms: int, amenities: int, photos: int} */
    private array $counts = ['types' => 0, 'rooms' => 0, 'amenities' => 0, 'photos' => 0];

    public function __construct(
        private readonly Connection $source,
        private readonly Connection $target,
        private readonly Filesystem $sourcePhotos,
        private readonly Filesystem $targetPhotos,
    ) {}

    /**
     * @param  bool  $apply  false = dry run: report what would change, change nothing
     * @return array{report: list<string>, counts: array{types: int, rooms: int, amenities: int, photos: int}}
     */
    public function run(bool $apply): array
    {
        $uploads = [];
        $work = function () use ($apply, &$uploads): void {
            $this->copyAmenities($apply);
            $targetAmenities = $this->target->table('amenities')->pluck('id', 'name');

            foreach ($this->source->table('accommodation_types')->where('is_demo', false)->orderBy('sort_order')->orderBy('id')->get() as $type) {
                $targetTypeId = $this->copyType($type, $apply);
                $this->copyRooms($type, $targetTypeId, $apply);
                $this->copyTickedAmenities($type, $targetTypeId, $targetAmenities, $apply);
                $uploads = [...$uploads, ...$this->copyPhotoRows($type, $targetTypeId, $apply)];
            }
        };

        $apply ? $this->target->transaction($work) : $work();

        // Files go up once the rows are safely saved.
        foreach ($uploads as [$sourceId, $targetId]) {
            foreach ($this->sourcePhotos->allFiles((string) $sourceId) as $file) {
                $this->targetPhotos->put($targetId.substr($file, strlen((string) $sourceId)), (string) $this->sourcePhotos->get($file));
            }
        }

        return ['report' => $this->report, 'counts' => $this->counts];
    }

    private function copyAmenities(bool $apply): void
    {
        foreach ($this->source->table('amenities')->where('is_demo', false)->orderBy('sort_order')->get() as $amenity) {
            $values = collect((array) $amenity)->only(self::AMENITY_COLUMNS)->all();
            $existing = $this->target->table('amenities')->where('name', $amenity->name)->first();

            if ($existing === null) {
                $this->note("+ amenity {$amenity->name}");
                $this->counts['amenities']++;
                if ($apply) {
                    $this->target->table('amenities')->insert($values + ['name' => $amenity->name, 'created_at' => now(), 'updated_at' => now()]);
                }
            } elseif ($this->changed($existing, $values) !== []) {
                $this->note("~ amenity {$amenity->name}: ".implode(', ', $this->changed($existing, $values)));
                $this->counts['amenities']++;
                if ($apply) {
                    $this->target->table('amenities')->where('id', $existing->id)->update($values + ['updated_at' => now()]);
                }
            }
        }
    }

    private function copyType(object $type, bool $apply): int
    {
        $values = collect((array) $type)->only(self::TYPE_COLUMNS)->all();
        $existing = $this->target->table('accommodation_types')->where('slug', $type->slug)->first();

        if ($existing === null) {
            $this->note("+ room type {$type->name} ({$type->slug})");
            $this->counts['types']++;

            return $apply ? (int) $this->target->table('accommodation_types')->insertGetId($values + ['created_at' => now(), 'updated_at' => now()]) : 0;
        }

        $changed = $this->changed($existing, $values);
        if ($changed !== []) {
            $this->note("~ room type {$type->name} ({$type->slug}): ".implode(', ', $changed));
            $this->counts['types']++;
            if ($apply) {
                $this->target->table('accommodation_types')->where('id', $existing->id)->update($values + ['updated_at' => now()]);
            }
        }

        return (int) $existing->id;
    }

    private function copyRooms(object $type, int $targetTypeId, bool $apply): void
    {
        foreach ($this->source->table('rooms')->where('accommodation_type_id', $type->id)->orderBy('sort_order')->get() as $room) {
            $values = ['accommodation_type_id' => $targetTypeId, 'name' => $room->name, 'internal_notes' => $room->internal_notes, 'sort_order' => $room->sort_order, 'is_active' => $room->is_active, 'is_demo' => $room->is_demo];
            $existing = $this->target->table('rooms')->where('code', $room->code)->first();

            if ($existing === null) {
                $this->note("    + room {$room->name} ({$room->code})");
                $this->counts['rooms']++;
                if ($apply) {
                    $this->target->table('rooms')->insert($values + ['code' => $room->code, 'created_at' => now(), 'updated_at' => now()]);
                }
            } elseif ($this->changed($existing, $targetTypeId === 0 ? array_diff_key($values, ['accommodation_type_id' => 0]) : $values) !== []) {
                $this->note("    ~ room {$room->name} ({$room->code})");
                $this->counts['rooms']++;
                if ($apply) {
                    $this->target->table('rooms')->where('id', $existing->id)->update($values + ['updated_at' => now()]);
                }
            }
        }
    }

    /**
     * @param  Collection<string, int>  $targetAmenities  name => id
     */
    private function copyTickedAmenities(object $type, int $targetTypeId, Collection $targetAmenities, bool $apply): void
    {
        $names = $this->source->table('accommodation_type_amenity')
            ->join('amenities', 'amenities.id', '=', 'accommodation_type_amenity.amenity_id')
            ->where('accommodation_type_amenity.accommodation_type_id', $type->id)
            ->pluck('amenities.name');

        $missing = $names->reject(fn (string $name): bool => $targetAmenities->has($name));
        if ($missing->isNotEmpty()) {
            $this->note('    ! not on the target (demo amenities?): '.$missing->implode(', '));
        }

        if (! $apply || $targetTypeId === 0) {
            return;
        }

        $this->target->table('accommodation_type_amenity')->where('accommodation_type_id', $targetTypeId)->delete();
        foreach ($names->map(fn (string $name): ?int => $targetAmenities->get($name))->filter() as $amenityId) {
            $this->target->table('accommodation_type_amenity')->insert(['accommodation_type_id' => $targetTypeId, 'amenity_id' => $amenityId]);
        }
    }

    /**
     * @return list<array{0: int, 1: int}> source media id => new target media id, for uploading
     */
    private function copyPhotoRows(object $type, int $targetTypeId, bool $apply): array
    {
        $uploads = [];

        foreach ($this->source->table('media')->where('model_type', self::TYPE_MODEL)->where('model_id', $type->id)->where('collection_name', 'gallery')->orderBy('order_column')->get() as $media) {
            if ($media->disk !== 'public') {
                $this->note("    ! photo {$media->file_name} is not on the public disk, skipped");

                continue;
            }

            $exists = $targetTypeId !== 0 && $this->target->table('media')
                ->where('model_type', self::TYPE_MODEL)->where('model_id', $targetTypeId)
                ->where('collection_name', 'gallery')->where('file_name', $media->file_name)->exists();

            if ($exists) {
                continue;
            }

            $this->note("    + photo {$media->file_name}");
            $this->counts['photos']++;

            if ($apply) {
                $row = collect((array) $media)->except(['id', 'uuid', 'model_id', 'created_at', 'updated_at'])->all();
                $uploads[] = [(int) $media->id, (int) $this->target->table('media')->insertGetId($row + ['uuid' => (string) Str::uuid(), 'model_id' => $targetTypeId, 'created_at' => now(), 'updated_at' => now()])];
            }
        }

        return $uploads;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function changed(object $existing, array $values): array
    {
        return collect($values)
            ->filter(fn (mixed $value, string $column): bool => $this->normalise($existing->{$column} ?? null) !== $this->normalise($value))
            ->keys()
            ->values()
            ->all();
    }

    private function normalise(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? '1' : '0',
            $value === null => '',
            default => (string) $value,
        };
    }

    private function note(string $line): void
    {
        $this->report[] = $line;
    }
}
