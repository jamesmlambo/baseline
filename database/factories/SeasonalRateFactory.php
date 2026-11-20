<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AccommodationType;
use App\Models\SeasonalRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonalRate>
 */
class SeasonalRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'accommodation_type_id' => AccommodationType::factory(),
            'name' => 'Demo season',
            'starts_on' => now()->addMonth()->startOfMonth()->toDateString(),
            'ends_on' => now()->addMonth()->endOfMonth()->toDateString(),
            'nightly_rate_cents' => 150_000,
            'min_nights' => null,
        ];
    }
}
