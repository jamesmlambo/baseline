<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Channel;
use Illuminate\Database\Seeder;

/**
 * The approved OTA targets (DEC-03): Booking.com and LekkeSlaap. Created switched off and not
 * connected until a channel manager is chosen (PEND-07/08). Never overwrites edits.
 */
class ChannelSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['booking_com' => 'Booking.com', 'lekkeslaap' => 'LekkeSlaap'] as $code => $name) {
            Channel::query()->firstOrCreate(['code' => $code], ['name' => $name, 'driver' => 'null', 'is_enabled' => false]);
        }
    }
}
