<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Creates the website's system pages (FINAL_SPECIFICATION §5.1). Safe in every environment:
 * existing pages are never overwritten. Placeholder text is flagged is_demo until the
 * client supplies real content (WEB-08). The official communication policy is real content
 * because it is a confirmed requirement (DEC, §17 of SPEC v5.3).
 */
class WebsiteContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $page) {
            Page::query()->firstOrCreate(['key' => $page['key']], $page);
        }

        foreach ($this->settings() as $setting) {
            Setting::query()->firstOrCreate(['key' => $setting['key']], $setting);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pages(): array
    {
        $demo = '<p><strong>DEMO — replace before launch.</strong> The guesthouse has not yet supplied this content.</p>';

        return [
            $this->page('about', 'about-us', 'About Us', 'About', 'Get to know Baseline Lodge.', $demo.'<p>Tell the story of the lodge, the hosts and the area here.</p>'),
            $this->page('features', 'features-and-amenities', 'Features & Amenities', 'Features', 'What you will find in the rooms and around the lodge.', $demo),
            $this->page('services', 'services', 'Services', 'Services', 'Day passes, pool, braai, VIP areas and spa.', $demo.'<p>Services can be booked from Milestone 10. Contact us in the meantime.</p>'),
            $this->page('pool', 'swimming-pool-and-day-passes', 'Swimming Pool & Day Passes', 'Pool & day passes', 'Spend the day by the pool.', $demo.'<p>Opening hours, prices and capacity still to be confirmed.</p>'),
            $this->page('braai', 'braai-facilities', 'Braai Facilities', 'Braai', 'Braai areas for guests and day visitors.', $demo),
            $this->page('vip', 'vip-areas', 'VIP Areas', 'VIP areas', 'VIP 1 and VIP 2 for private gatherings.', $demo),
            $this->page('spa', 'spa-and-massage', 'Spa & Massage', 'Spa', 'Treatments and massages.', $demo.'<p>Treatment menu and prices still to be confirmed.</p>'),
            $this->page('contact', 'contact-us', 'Contact Us', 'Contact', 'Send us a message or reach us on WhatsApp.', '<p>Send us a message and the team will get back to you.</p>', isDemo: false),
            $this->page('guest-portal', 'guest-portal', 'Guest Portal', 'Guest portal', 'Services during your stay, from your phone.', '<p>During your stay, scan the QR code in your room and enter the code we send to the phone number on your booking. You can then order meals, request towels and toiletries, message reception and check your bill.</p><p>The guest portal opens with Milestone 7.</p>', isDemo: false),
            $this->page('house-rules', 'house-rules', 'House Rules', 'House rules', 'Please read before you arrive.', $demo.'<p>House rules are pending a client decision (PEND-05).</p>'),
            $this->page('policies', 'booking-and-cancellation-policies', 'Booking & Cancellation Policies', 'Booking policies', 'Deposits, cancellations and no-shows.', $demo.'<p>Deposit, cancellation and no-show policies are pending a client decision (PEND-05).</p>'),
            $this->page('privacy', 'privacy-policy', 'Privacy Policy', 'Privacy', 'How we handle your personal information.', $demo.'<p>Privacy notice wording must be checked by a qualified South African adviser for POPIA compliance before launch.</p>'),
            $this->page('communication-policy', 'official-communication-policy', 'Official Communication Policy', 'Communication policy', 'How to recognise genuine messages from Baseline Lodge.', $this->communicationPolicy(), isDemo: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function page(string $key, string $slug, string $title, string $navLabel, string $summary, string $body, bool $isDemo = true): array
    {
        return [
            'key' => $key,
            'slug' => $slug,
            'title' => $title,
            'nav_label' => $navLabel,
            'summary' => $summary,
            'body' => $body,
            'meta_description' => $summary,
            'is_published' => true,
            'is_demo' => $isDemo,
        ];
    }

    private function communicationPolicy(): string
    {
        return <<<'HTML'
            <p>Fraudsters sometimes pretend to be guesthouses to steal money or card details. To keep you safe:</p>
            <ul>
                <li><strong>We will never ask for your card number, expiry date or CVV by WhatsApp, SMS, email or chat.</strong></li>
                <li>Card payments are only ever made on a secure payment page from our approved payment provider.</li>
                <li>We will never ask you to pay into a different bank account by message. If our banking details ever seem to have changed, call us on our published number before you pay.</li>
                <li>Room access codes for our guest portal are only sent to the phone number on your booking. Never share them.</li>
            </ul>
            <p>If you receive a suspicious message claiming to be from Baseline Lodge, do not reply or pay. Contact us using the details on this website.</p>
            HTML;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function settings(): array
    {
        return [
            [
                'group' => 'website', 'key' => 'website.tagline', 'label' => 'Homepage tagline', 'type' => 'string',
                'value' => null, 'is_pending_client_decision' => true, 'decision_reference' => 'PEND-04',
                'help_text' => 'Short line under the name on the homepage. Not yet supplied.',
            ],
            [
                'group' => 'website', 'key' => 'website.intro', 'label' => 'Homepage introduction', 'type' => 'text',
                'value' => null, 'is_pending_client_decision' => true, 'decision_reference' => 'PEND-04',
                'help_text' => 'A paragraph introducing the lodge. Not yet supplied.',
            ],
            [
                'group' => 'website', 'key' => 'website.grading_stars', 'label' => 'Tourism grading stars', 'type' => 'integer',
                'value' => null, 'is_pending_client_decision' => true, 'decision_reference' => 'WEB-07',
                'help_text' => 'Only shown on the website once valid grading evidence has been checked (set "Grading evidence verified").',
            ],
            [
                'group' => 'website', 'key' => 'website.grading_verified', 'label' => 'Grading evidence verified (yes/no)', 'type' => 'string',
                'value' => 'no', 'is_pending_client_decision' => true, 'decision_reference' => 'WEB-07',
                'help_text' => 'Type "yes" only after seeing a valid TGCSA grading certificate.',
            ],
            [
                'group' => 'website', 'key' => 'website.enquiry_email', 'label' => 'Email for website enquiries', 'type' => 'string',
                'value' => null, 'is_pending_client_decision' => true, 'decision_reference' => 'PEND-04',
                'help_text' => 'Where new contact-form and booking-request notifications are sent. Enquiries are always visible in admin.',
            ],
        ];
    }
}
