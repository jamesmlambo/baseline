<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SettingSeeder::class,
            AccommodationSeeder::class,
            AmenitySeeder::class,
            WebsiteContentSeeder::class,
            MessageTemplateSeeder::class,
            TaskChecklistSeeder::class,
            ChannelSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call([
                DevelopmentUserSeeder::class,
                DemoAccommodationSeeder::class,
                DemoWebsiteSeeder::class,
                DemoMenuSeeder::class,
                DemoStaffSeeder::class,
                DemoLeisureSeeder::class,
            ]);
        }
    }
}
