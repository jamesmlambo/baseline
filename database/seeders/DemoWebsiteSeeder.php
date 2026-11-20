<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AmenityCategory;
use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\AccommodationType;
use App\Models\Amenity;
use App\Models\Faq;
use App\Models\Post;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local-only DEMO news, FAQs and amenities so the website can be reviewed. All flagged is_demo.
 * The facilities named (pool, braai, VIP 1/2, spa) are confirmed in SPEC v5.3 §3; details are not.
 */
class DemoWebsiteSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoWebsiteSeeder may only run in the local environment.');
        }

        $amenities = [
            ['Wi-Fi', AmenityCategory::Room, 'heroicon-o-wifi'],
            ['Kettle with coffee & tea', AmenityCategory::Room, 'heroicon-o-beaker'],
            ['Microwave', AmenityCategory::Room, 'heroicon-o-fire'],
            ['Fresh towels', AmenityCategory::Room, 'heroicon-o-sparkles'],
            ['Swimming pool', AmenityCategory::Property, 'heroicon-o-sun'],
            ['Braai area', AmenityCategory::Property, 'heroicon-o-fire'],
            ['VIP areas', AmenityCategory::Property, 'heroicon-o-star'],
            ['Spa & massage', AmenityCategory::Property, 'heroicon-o-heart'],
        ];

        foreach ($amenities as $index => [$name, $category, $icon]) {
            Amenity::query()->updateOrCreate(['name' => $name], [
                'category' => $category,
                'icon' => $icon,
                'description' => 'DEMO — confirm details with the guesthouse.',
                'sort_order' => $index,
                'is_published' => true,
                'is_demo' => true,
            ]);
        }

        $roomAmenityIds = Amenity::query()->where('category', AmenityCategory::Room)->pluck('id');
        AccommodationType::query()->where('is_demo', true)->each(
            fn (AccommodationType $type) => $type->amenities()->syncWithoutDetaching($roomAmenityIds),
        );

        $faqs = [
            ['Bookings', 'What time is check-in and check-out?', 'DEMO — check-in and check-out times are still to be confirmed by the guesthouse.'],
            ['Bookings', 'How do I pay?', 'Instant EFT is shown first. Ordinary EFT and card payments are also accepted. We never ask for card details by WhatsApp or SMS.'],
            ['Facilities', 'Can day visitors use the pool and braai areas?', 'DEMO — day passes are planned. Prices and hours to be confirmed.'],
            ['During your stay', 'How do I order meals or extra towels?', 'Scan the QR code in your room and enter the code sent to your booking phone number.'],
        ];

        foreach ($faqs as $index => [$category, $question, $answer]) {
            Faq::query()->updateOrCreate(['question' => $question], [
                'category' => $category,
                'answer' => $answer,
                'sort_order' => $index,
                'is_published' => true,
                'is_demo' => true,
            ]);
        }

        $posts = [
            [PostType::News, 'Our new website is on its way', 'demo-new-website', 'We are building a new website with online booking.', 3],
            [PostType::Event, 'Demo: Sunday braai day', 'demo-sunday-braai-day', 'A placeholder event to show how events appear.', 2],
            [PostType::Promotion, 'Demo: mid-week special', 'demo-mid-week-special', 'A placeholder promotion. Prices are not real.', 1],
        ];

        foreach ($posts as [$type, $title, $slug, $excerpt, $daysAgo]) {
            Post::query()->updateOrCreate(['slug' => $slug], [
                'type' => $type,
                'status' => PostStatus::Published,
                'title' => $title,
                'excerpt' => $excerpt,
                'body' => '<p><strong>DEMO — replace before launch.</strong></p><p>'.$excerpt.'</p>',
                'event_starts_at' => $type === PostType::Event ? now()->addWeeks(2)->setTime(12, 0) : null,
                'event_location' => $type === PostType::Event ? 'Braai area' : null,
                'published_at' => now()->subDays($daysAgo),
                'is_demo' => true,
            ]);
        }
    }
}
