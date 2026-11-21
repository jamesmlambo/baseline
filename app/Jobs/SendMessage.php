<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Services\Messaging\MessageProviderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Hands one queued message to its provider, retrying a limited number of times (MSG-05).
 */
class SendMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $messageId) {}

    public function handle(MessageProviderManager $providers): void
    {
        $message = Message::query()->with('template')->find($this->messageId);

        if ($message === null || $message->status !== MessageStatus::Queued) {
            return;
        }

        $message->increment('attempts');
        $provider = $providers->for($message->channel);

        try {
            $receipt = $provider->send((string) $message->recipient, $message->body, $message->template?->provider_template_id);
        } catch (Throwable $exception) {
            $message->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 1000)])->save();

            throw $exception;
        }

        $message->forceFill([
            'status' => $receipt->delivered ? MessageStatus::Delivered : MessageStatus::Sent,
            'provider' => $provider->name(),
            'provider_message_id' => $receipt->providerMessageId,
            'cost_cents' => $receipt->cost,
            'sent_at' => now(),
            'delivered_at' => $receipt->delivered ? now() : null,
            'last_error' => null,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        Message::query()->whereKey($this->messageId)->update([
            'status' => MessageStatus::Failed,
            'last_error' => $exception !== null ? mb_substr($exception->getMessage(), 0, 1000) : 'Failed',
            'cost_cents' => 0,
            'updated_at' => now(),
        ]);
    }
}
