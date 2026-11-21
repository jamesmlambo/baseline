<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\MessageChannel;
use App\Enums\MessageClassification;
use App\Enums\MessageStatus;
use App\Enums\StaffRole;
use App\Jobs\SendMessage;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\MessagingSpendAlert;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * The only way the system sends SMS or WhatsApp (MSG-01..06). Every attempt is stored in
 * `messages`, including ones deliberately not sent and why, so staff can see exactly what
 * a guest received.
 */
class Messenger
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly SpendGuard $spend,
    ) {}

    /**
     * @param  array<string, string|int|null>  $variables
     * @param  string  $idempotencyKey  the same key never sends twice (e.g. "payment_confirmed:42")
     */
    public function send(
        string $templateKey,
        ?string $phone,
        array $variables,
        string $idempotencyKey,
        ?Booking $booking = null,
        ?Guest $guest = null,
        ?MessageChannel $channel = null,
        ?User $sentBy = null,
    ): ?Message {
        $recipient = Guest::normalisePhone($phone);

        if ($recipient === null) {
            return null;
        }

        $existing = Message::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return $existing;
        }

        $channel ??= self::preferredChannel();
        $guest ??= $booking?->guest;
        $template = $this->template($templateKey, $channel);

        [$status, $reason, $body] = $this->decide($template, $guest, $channel, $variables);

        try {
            $message = DB::transaction(fn (): Message => Message::query()->create([
                'message_template_id' => $template?->id,
                'template_version' => $template?->version,
                'channel' => $channel,
                'classification' => $template?->classification ?? MessageClassification::Operational,
                'recipient' => $recipient,
                'recipient_hash' => Guest::lookupHash('phone', $recipient),
                'body' => $body,
                'status' => $status,
                'skip_reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'booking_id' => $booking?->id,
                'guest_id' => $guest?->id,
                'cost_cents' => $status === MessageStatus::Queued ? ($template?->cost_estimate_cents ?? Money::zero()) : Money::zero(),
                'sent_by' => $sentBy?->id,
            ]));
        } catch (QueryException $exception) {
            // Two automations raced on the same key: the other one won, so do nothing.
            if ((string) $exception->getCode() === '23505') {
                return Message::query()->where('idempotency_key', $idempotencyKey)->first();
            }

            throw $exception;
        }

        if ($status === MessageStatus::Queued) {
            DB::afterCommit(fn () => SendMessage::dispatch($message->id));
            $this->alertOwnerIfNearCap($channel);
        }

        return $message;
    }

    /**
     * Staff resend (MSG-05): same wording, optionally on the other channel, as a new logged message.
     */
    public function resend(Message $original, User $by, ?MessageChannel $channel = null): ?Message
    {
        $channel ??= $original->channel;
        $template = $original->template;

        if ($template !== null && $template->channel !== $channel) {
            $template = $this->template($template->key, $channel) ?? $template;
        }

        $message = Message::query()->create([
            'message_template_id' => $template?->id,
            'template_version' => $template?->version,
            'channel' => $channel,
            'classification' => $original->classification,
            'recipient' => $original->recipient,
            'recipient_hash' => $original->recipient_hash,
            'body' => $original->body,
            'status' => MessageStatus::Queued,
            'idempotency_key' => 'resend:'.$original->id.':'.now()->format('YmdHisv'),
            'booking_id' => $original->booking_id,
            'guest_id' => $original->guest_id,
            'cost_cents' => $template?->cost_estimate_cents ?? Money::zero(),
            'sent_by' => $by->id,
        ]);

        DB::afterCommit(fn () => SendMessage::dispatch($message->id));

        return $message;
    }

    public static function preferredChannel(): MessageChannel
    {
        return MessageChannel::tryFrom((string) Setting::get('messaging.preferred_channel', 'whatsapp')) ?? MessageChannel::WhatsApp;
    }

    private function template(string $key, MessageChannel $channel): ?MessageTemplate
    {
        return MessageTemplate::query()->where('key', $key)->where('channel', $channel)->where('language', 'en')->first();
    }

    /**
     * @param  array<string, string|int|null>  $variables
     * @return array{0: MessageStatus, 1: ?string, 2: string}
     */
    private function decide(?MessageTemplate $template, ?Guest $guest, MessageChannel $channel, array $variables): array
    {
        if ($template === null) {
            return [MessageStatus::Skipped, 'No wording set up for this message on '.$channel->label(), ''];
        }

        if (! $template->is_active) {
            return [MessageStatus::Skipped, 'Message switched off', ''];
        }

        try {
            $body = $this->renderer->render($template, $variables);
        } catch (InvalidArgumentException $exception) {
            return [MessageStatus::Skipped, $exception->getMessage(), ''];
        }

        if ($guest?->hasOptedOutOf($channel)) {
            return [MessageStatus::Skipped, "Guest opted out of {$channel->label()}", $body];
        }

        if ($template->classification === MessageClassification::Marketing && ! $guest?->marketing_consent) {
            return [MessageStatus::Skipped, 'No marketing consent', $body];
        }

        if ($this->spend->blocks($channel, $template->cost_estimate_cents, $template->is_critical)) {
            return [MessageStatus::Skipped, 'Monthly spend cap reached', $body];
        }

        return [MessageStatus::Queued, null, $body];
    }

    private function alertOwnerIfNearCap(MessageChannel $channel): void
    {
        if (! $this->spend->shouldAlert($channel)) {
            return;
        }

        $cacheKey = 'messaging.spend_alert.'.$channel->value.'.'.now()->format('Y-m');

        if (! Cache::add($cacheKey, true, now()->endOfMonth())) {
            return;
        }

        $owners = User::query()->role(StaffRole::Owner->value)->where('is_active', true)->get();

        Notification::send($owners, new MessagingSpendAlert(
            $channel,
            $this->spend->spentThisMonth($channel),
            $this->spend->cap($channel) ?? Money::zero(),
        ));
    }
}
