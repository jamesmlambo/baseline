<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AccommodationType;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        $code = 'R'.Str::upper(Str::random(6));

        return [
            'accommodation_type_id' => AccommodationType::factory(),
            'name' => 'Demo Room '.$code,
            'code' => $code,
            'sort_order' => 0,
            'is_active' => true,
            'is_demo' => true,
        ];
    }
}
