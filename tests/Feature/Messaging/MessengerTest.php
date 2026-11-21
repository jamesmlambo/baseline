<?php

declare(strict_types=1);

require_once __DIR__.'/../Finance/FinanceTestHelpers.php';

use App\Enums\MessageChannel;
use App\Enums\MessageClassification;
use App\Enums\MessageStatus;
use App\Enums\StaffRole;
use App\Jobs\SendMessage;
use App\Models\Guest;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Setting;
use App\Notifications\MessagingSpendAlert;
use App\Services\Messaging\Contracts\MessageProvider;
use App\Services\Messaging\MessageProviderManager;
use App\Services\Messaging\Messenger;
use App\Services\Messaging\ProviderReceipt;
use App\Support\Money;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    seedFinanceSettings();
    $this->seed(MessageTemplateSeeder::class);
    Mail::fake();
});

function farewellTo(string $phone, string $key = 'test:1', ?Guest $guest = null, ?MessageChannel $channel = null): ?Message
{
    return app(Messenger::class)->send('farewell', $phone, ['guest_name' => 'Lerato'], $key, guest: $guest, channel: $channel);
}

it('MSG-01 sends through the log driver and stores the message with the recipient encrypted', function (): void {
    $message = farewellTo('082 555 0101');

    expect($message->fresh())
        ->status->toBe(MessageStatus::Delivered)
        ->channel->toBe(MessageChannel::WhatsApp)
        ->provider->toBe('log')
        ->body->toContain('Thank you for staying with us, Lerato')
        ->and(DB::table('messages')->value('recipient'))->not->toContain('825550101');
});

it('MSG-02 never sends the same automated message twice', function (): void {
    farewellTo('082 555 0101', 'farewell:7');
    farewellTo('082 555 0101', 'farewell:7');

    expect(Message::query()->count())->toBe(1);
});

it('does not send a message with a missing value, and records why', function (): void {
    $message = app(Messenger::class)->send('farewell', '082 555 0101', [], 'test:missing');

    expect($message)
        ->status->toBe(MessageStatus::Skipped)
        ->skip_reason->toContain('guest_name');
});

it('sends nothing without a phone number', function (): void {
    expect(farewellTo(''))->toBeNull()
        ->and(Message::query()->count())->toBe(0);
});

it('MSG-04 respects a guest opting out of a channel', function (): void {
    $guest = Guest::factory()->create(['channel_opt_outs' => ['whatsapp']]);

    expect(farewellTo('082 555 0101', 'a', $guest)->status)->toBe(MessageStatus::Skipped)
        ->and(farewellTo('082 555 0101', 'b', $guest, MessageChannel::Sms)->fresh()->status)->toBe(MessageStatus::Delivered);
});

it('MSG-04 sends marketing messages only with consent', function (): void {
    MessageTemplate::query()->create([
        'key' => 'special_offer', 'channel' => MessageChannel::WhatsApp, 'name' => 'Offer',
        'body' => 'Hi {{ guest_name }}, winter special!', 'classification' => MessageClassification::Marketing,
    ]);
    $send = fn (Guest $guest, string $key) => app(Messenger::class)->send('special_offer', '082 555 0101', ['guest_name' => 'L'], $key, guest: $guest);

    expect($send(Guest::factory()->create(['marketing_consent' => false]), 'x')->skip_reason)->toBe('No marketing consent')
        ->and($send(Guest::factory()->create(['marketing_consent' => true]), 'y')->fresh()->status)->toBe(MessageStatus::Delivered);
});

it('MSG-06 stops non-critical messages at the monthly cap, still sends critical ones, and alerts the owner once', function (): void {
    Notification::fake();
    $owner = staffUser(StaffRole::Owner);
    Setting::query()->where('key', 'messaging.monthly_cap_whatsapp_cents')->firstOrFail()->update(['value' => 1_000]);
    MessageTemplate::query()->where('channel', MessageChannel::WhatsApp)->update(['cost_estimate_cents' => 450]);
    app()->instance(MessageProviderManager::class, new class extends MessageProviderManager
    {
        public function for(MessageChannel $channel): MessageProvider
        {
            return new class($channel) implements MessageProvider
            {
                public function __construct(private MessageChannel $channel) {}

                public function name(): string
                {
                    return 'priced';
                }

                public function channel(): MessageChannel
                {
                    return $this->channel;
                }

                public function send(string $to, string $body, ?string $providerTemplateId = null): ProviderReceipt
                {
                    return new ProviderReceipt('p_'.uniqid(), Money::cents(450));
                }
            };
        }
    });

    farewellTo('082 555 0101', 'k1');
    farewellTo('082 555 0102', 'k2');
    $blocked = farewellTo('082 555 0103', 'k3');
    $critical = app(Messenger::class)->send('payment_confirmed', '082 555 0104', [
        'guest_name' => 'L', 'reference' => 'BL-X', 'arrival_date' => 'a', 'departure_date' => 'd', 'booking_link' => 'https://x.test',
    ], 'k4');

    expect($blocked->skip_reason)->toBe('Monthly spend cap reached')
        ->and($critical->fresh()->status)->toBe(MessageStatus::Sent);

    Notification::assertSentToTimes($owner, MessagingSpendAlert::class, 1);
});

it('MSG-05 retries a failing provider and then marks the message failed', function (): void {
    $message = Message::query()->create([
        'channel' => MessageChannel::Sms, 'classification' => MessageClassification::Operational, 'recipient' => '+27825550101',
        'recipient_hash' => 'x', 'body' => 'Hi', 'status' => MessageStatus::Queued, 'idempotency_key' => 'fail:1', 'cost_cents' => 0,
    ]);
    app()->instance(MessageProviderManager::class, new class extends MessageProviderManager
    {
        public function for(MessageChannel $channel): MessageProvider
        {
            throw new RuntimeException('Provider down');
        }
    });
    $job = new SendMessage($message->id);

    expect(fn () => $job->handle(app(MessageProviderManager::class)))->toThrow(RuntimeException::class);
    $job->failed(new RuntimeException('Provider down'));

    expect($message->fresh())
        ->status->toBe(MessageStatus::Failed)
        ->last_error->toBe('Provider down')
        ->attempts->toBe(1)
        ->and($job->tries)->toBe(3);
});

it('MSG-05 lets staff resend on the other channel as a new logged message', function (): void {
    $original = farewellTo('082 555 0101');

    $resent = app(Messenger::class)->resend($original, staffUser(StaffRole::Receptionist), MessageChannel::Sms);

    expect($resent->id)->not->toBe($original->id)
        ->and($resent->fresh()->channel)->toBe(MessageChannel::Sms)
        ->and($resent->fresh()->status)->toBe(MessageStatus::Delivered)
        ->and($resent->body)->toBe($original->body);
});

it('MSG-02 bumps the template version when the wording changes, and records it on messages', function (): void {
    $template = MessageTemplate::query()->where('key', 'farewell')->where('channel', MessageChannel::WhatsApp)->firstOrFail();
    $template->update(['body' => 'Go well, {{ guest_name }}!']);

    $message = farewellTo('082 555 0101');

    expect($template->fresh()->version)->toBe(2)
        ->and($message->template_version)->toBe(2)
        ->and($message->body)->toBe('Go well, Lerato!');
});
