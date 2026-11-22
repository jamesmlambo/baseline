<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AccommodationType;
use Illuminate\Database\Seeder;

/**
 * Baseline Lodge's real room types and rooms, as supplied by the client on 2026-11-22 (PEND-04).
 *
 * Prices and guest capacity were not supplied: the owner sets them under Bookings › Rooms & rates.
 * Every type therefore starts hidden from guests ("Bookable" off) at R0.00 until it has been priced.
 *
 * Safe to run more than once: existing types and rooms are never changed, so anything the owner
 * has edited (prices, names, photos, visibility) is kept.
 */
class AccommodationSeeder extends Seeder
{
    /**
     * Rooms without a supplied number get a placeholder label for staff to rename.
     */
    public const PENDING_ROOM_NOTE = 'PENDING CLIENT DECISION: room name or number not supplied yet. Rename here.';

    /**
     * Each type lists its rooms as code => name, in the order the client gave them.
     *
     * @var list<array{name: string, slug: string, rooms: array<string, string>, pending?: bool}>
     */
    public const TYPES = [
        ['name' => 'Family Luxury Room', 'slug' => 'family-luxury-room', 'rooms' => ['FAMILY-LUXURY' => 'Family Luxury Room #1']],
        ['name' => 'Vista Room', 'slug' => 'vista-room', 'rooms' => ['VISTA' => 'Vista Room #1']],
        ['name' => 'Studio Apartment Room', 'slug' => 'studio-apartment-room', 'rooms' => ['SKYVIEW' => 'Skyview', '18' => 'Room 18']],
        ['name' => 'Premium Room - Honeymoon Suite', 'slug' => 'premium-room-honeymoon-suite', 'rooms' => ['HONEYMOON' => 'Honeymoon Suite #1']],
        ['name' => 'Premium Room - Villa Suite', 'slug' => 'premium-room-villa-suite', 'rooms' => ['VILLA' => 'Villa Suite #1']],
        ['name' => 'Budget Room', 'slug' => 'budget-room', 'rooms' => ['5' => 'Room 5', '4' => 'Room 4']],
        ['name' => 'Twin Double Bed Room', 'slug' => 'twin-double-bed-room', 'rooms' => ['TWIN-1' => 'Twin Double Bed Room 1', 'TWIN-2' => 'Twin Double Bed Room 2'], 'pending' => true],
        ['name' => 'Luxury Room', 'slug' => 'luxury-room', 'rooms' => [
            '19' => 'Room 19', '2' => 'Room 2', '3' => 'Room 3', '17' => 'Room 17',
            '9' => 'Room 9', '22' => 'Room 22', '15' => 'Room 15', '20' => 'Room 20',
        ]],
        ['name' => 'Standard Room', 'slug' => 'standard-room', 'rooms' => [
            '10' => 'Room 10', '11' => 'Room 11', '12' => 'Room 12', '6' => 'Room 6',
            '8' => 'Room 8', '16' => 'Room 16', '21' => 'Room 21',
        ]],
    ];

    public function run(): void
    {
        $roomOrder = 0;

        foreach (self::TYPES as $typeOrder => $definition) {
            $type = AccommodationType::query()->firstOrCreate(['slug' => $definition['slug']], [
                'name' => $definition['name'],
                'base_nightly_rate_cents' => 0,
                'extra_guest_nightly_cents' => 0,
                'sort_order' => $typeOrder + 1,
                'is_active' => false,
                'is_demo' => false,
            ]);

            foreach ($definition['rooms'] as $code => $name) {
                $type->rooms()->firstOrCreate(['code' => (string) $code], [
                    'name' => $name,
                    'internal_notes' => ($definition['pending'] ?? false) ? self::PENDING_ROOM_NOTE : null,
                    'sort_order' => ++$roomOrder,
                    'is_active' => true,
                    'is_demo' => false,
                ]);
            }
        }
    }
}
