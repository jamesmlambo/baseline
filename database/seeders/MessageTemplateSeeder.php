<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MessageChannel;
use App\Enums\MessageClassification;
use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

/**
 * Default message wordings (MSG-02, MSG-03). Safe in every environment: existing templates,
 * including staff edits, are never overwritten. Costs start at R0 until a provider is chosen.
 * WhatsApp business-initiated messages will also need the provider's template approval.
 */
class MessageTemplateSeeder extends Seeder
{
    private const SAFETY = 'We will never ask for card details by WhatsApp or SMS.';

    public function run(): void
    {
        foreach ($this->templates() as [$key, $name, $body, $classification, $critical]) {
            foreach (MessageChannel::cases() as $channel) {
                MessageTemplate::query()->firstOrCreate(
                    ['key' => $key, 'channel' => $channel, 'language' => 'en'],
                    [
                        'name' => $name,
                        'body' => $body,
                        'classification' => $classification,
                        'is_critical' => $critical,
                        'is_active' => $key !== 'feedback_request',
                        'cost_estimate_cents' => 0,
                    ],
                );
            }
        }
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: MessageClassification, 4: bool}>
     */
    private function templates(): array
    {
        $op = MessageClassification::Operational;

        return [
            ['booking_received', 'Booking received — payment needed',
                'Hi {{ guest_name }}, thank you for booking with Baseline Lodge (ref {{ reference }}). Your room is held until {{ hold_deadline }}. Please pay {{ amount_due }} here: {{ booking_link }} '.self::SAFETY, $op, false],
            ['hold_reminder', 'Unpaid booking reminder',
                'Hi {{ guest_name }}, your room hold for booking {{ reference }} ends at {{ hold_deadline }}. Pay here to keep it: {{ booking_link }}', $op, false],
            ['payment_confirmed', 'Payment received — booking confirmed',
                'Hi {{ guest_name }}, payment received — booking {{ reference }} is confirmed for {{ arrival_date }} to {{ departure_date }}. Details and receipt: {{ booking_link }}', $op, true],
            ['pre_arrival', 'Pre-arrival information',
                'Hi {{ guest_name }}, we look forward to welcoming you to Baseline Lodge on {{ arrival_date }}. Your booking details: {{ booking_link }}', $op, false],
            ['departure_day', 'Departure-day reminder',
                'Good morning {{ guest_name }}, today is your departure day. You can review your account here: {{ booking_link }} or at reception.', $op, false],
            ['farewell', 'Thank you and safe travels',
                'Thank you for staying with us, {{ guest_name }}. Safe travels from everyone at Baseline Lodge!', $op, false],
            ['feedback_request', 'Feedback request (optional)',
                'Hi {{ guest_name }}, we hope you enjoyed your stay. We would love your feedback: {{ feedback_link }}', $op, false],
            ['price_list', 'WhatsApp price list',
                'Hi {{ customer_name }}, here are our available rooms and prices for {{ arrival_date }} to {{ departure_date }}: {{ price_list_link }} The link works until {{ expires_at }}. Rooms are only reserved once you book. '.self::SAFETY, $op, false],
            ['price_list_follow_up', 'Price-list follow-up',
                'Hi {{ customer_name }}, are you still planning a stay on {{ arrival_date }}? Our current prices: {{ price_list_link }}', $op, false],
        ];
    }
}
