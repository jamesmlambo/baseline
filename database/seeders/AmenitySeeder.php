<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AmenityCategory;
use App\Models\Amenity;
use Illuminate\Database\Seeder;

/**
 * Room amenities supplied by the client on 24 Nov 2026, in their order, each with a
 * Phosphor icon (MIT licence) shown before its name on the website and in the admin.
 * Staff tick them per room type under Bookings › Rooms & rates.
 *
 * Safe to run again: existing real amenities are left as staff edited them. A DEMO
 * amenity with the same name becomes the real one.
 */
class AmenitySeeder extends Seeder
{
    /**
     * @var list<array{0: string, 1: string}> name, icon
     */
    public const AMENITIES = [
        ['Shower', 'phosphor-shower-duotone'],
        ['Bathtub', 'phosphor-bathtub-duotone'],
        ['Fresh towels', 'phosphor-towel-duotone'],
        ['Bath mat', 'phosphor-footprints-duotone'],
        ['Hairdryer', 'phosphor-hair-dryer-duotone'],
        ['Single bed', 'phosphor-bed-duotone'],
        ['Double bed', 'phosphor-bed-duotone'],
        ['Queen bed', 'phosphor-bed-duotone'],
        ['King bed', 'phosphor-bed-duotone'],
        ['Twin beds', 'phosphor-bed-duotone'],
        ['Couch', 'phosphor-couch-duotone'],
        ['Balcony or patio', 'phosphor-sun-horizon-duotone'],
        ['Garden view', 'phosphor-tree-duotone'],
        ['Seating area', 'phosphor-armchair-duotone'],
        ['Daily housekeeping', 'phosphor-broom-duotone'],
        ['Housekeeping on request', 'phosphor-bell-simple-ringing-duotone'],
        ['Wheelchair-accessible room', 'phosphor-wheelchair-duotone'],
        ['Roll-in shower', 'phosphor-wheelchair-motion-duotone'],
        ['Handheld shower', 'phosphor-drop-duotone'],
        ['Accessible toilet', 'phosphor-toilet-duotone'],
        ['Free parking', 'phosphor-car-duotone'],
        ['Outdoor seating', 'phosphor-picnic-table-duotone'],
        ['Swimming pool', 'phosphor-swimming-pool-duotone'],
        ['Hot tub', 'phosphor-waves-duotone'],
        ['Laundry facilities', 'phosphor-washing-machine-duotone'],
        ['Luggage storage', 'phosphor-suitcase-rolling-duotone'],
        ['Wi-Fi', 'phosphor-wifi-high-duotone'],
        ['Air conditioning', 'phosphor-snowflake-duotone'],
        ['Heating', 'phosphor-thermometer-hot-duotone'],
        ['Kettle', 'phosphor-bowl-steam-duotone'],
        ['Tea and coffee', 'phosphor-coffee-duotone'],
        ['TV', 'phosphor-television-simple-duotone'],
        ['Bed linen', 'phosphor-shirt-folded-duotone'],
        ['Private bathroom', 'phosphor-door-duotone'],
        ['Toiletries', 'phosphor-hand-soap-duotone'],
        ['Mini-fridge', 'phosphor-thermometer-cold-duotone'],
        ['Microwave', 'phosphor-oven-duotone'],
        ['Desk or workspace', 'phosphor-desk-duotone'],
        ['Wardrobe and hangers', 'phosphor-coat-hanger-duotone'],
        ['Blackout curtains', 'phosphor-moon-stars-duotone'],
        ['Iron and ironing board', 'phosphor-t-shirt-duotone'],
        ['Safe', 'phosphor-vault-duotone'],
        ['Kitchenette', 'phosphor-cooking-pot-duotone'],
        ['Breakfast', 'phosphor-bread-duotone'],
        ['Pet-friendly', 'phosphor-paw-print-duotone'],
    ];

    public function run(): void
    {
        foreach (self::AMENITIES as $order => [$name, $icon]) {
            $amenity = Amenity::query()->firstOrNew(['name' => $name]);

            if ($amenity->exists && ! $amenity->is_demo) {
                continue;
            }

            if ($amenity->exists) {
                // A DEMO amenity becomes the real one: drop its placeholder description.
                $amenity->description = null;
            }

            $amenity->fill([
                'category' => AmenityCategory::Room,
                'icon' => $icon,
                'sort_order' => $order + 1,
                'is_published' => true,
                'is_demo' => false,
            ])->save();
        }
    }
}
