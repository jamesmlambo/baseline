<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AccommodationType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AccommodationType>
 */
class AccommodationTypeFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Demo Type '.Str::upper(Str::random(4));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'summary' => 'DEMO — replace before launch.',
            'base_occupancy' => 2,
            'max_occupancy' => 4,
            'base_nightly_rate_cents' => 100_000,
            'extra_guest_nightly_cents' => 20_000,
            'sort_order' => 0,
            'is_active' => true,
            'is_demo' => true,
        ];
    }

    public function withRooms(int $count): static
    {
        return $this->afterCreating(function (AccommodationType $type) use ($count): void {
            for ($i = 1; $i <= $count; $i++) {
                $type->rooms()->create([
                    'name' => sprintf('Demo Room %s-%02d', $type->id, $i),
                    'code' => sprintf('T%sR%02d', $type->id, $i),
                    'sort_order' => $i,
                    'is_active' => true,
                    'is_demo' => true,
                ]);
            }
        });
    }
}
