<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local-only DEMO room-service menu so the guest portal can be reviewed. Items and prices are
 * placeholders flagged is_demo: the real menu is PENDING CLIENT DECISION (PEND-04).
 */
class DemoMenuSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoMenuSeeder may only run in the local environment.');
        }

        $items = [
            ['Breakfast', 'DEMO Continental breakfast', 'Placeholder item — replace with the real menu.', 'Gluten, dairy', 9500],
            ['Breakfast', 'DEMO Full breakfast', 'Placeholder item — replace with the real menu.', 'Egg, gluten', 12000],
            ['Light meals', 'DEMO Toasted sandwich', 'Placeholder item — replace with the real menu.', 'Gluten, dairy', 7500],
            ['Light meals', 'DEMO Garden salad', 'Placeholder item — replace with the real menu.', null, 6500],
            ['Drinks', 'DEMO Soft drink', 'Placeholder item — replace with the real menu.', null, 2500],
        ];

        foreach ($items as $index => [$category, $name, $description, $allergens, $priceCents]) {
            MenuItem::query()->updateOrCreate(['name' => $name], [
                'category' => $category,
                'description' => $description,
                'allergens' => $allergens,
                'price_cents' => $priceCents,
                'is_available' => true,
                'is_demo' => true,
                'sort_order' => $index,
            ]);
        }
    }
}
