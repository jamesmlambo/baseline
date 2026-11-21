<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\MessageChannel;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * MSG-06: tells the owner when a channel reaches the alert threshold of its monthly cap.
 */
class MessagingSpendAlert extends Notification
{
    public function __construct(
        public readonly MessageChannel $channel,
        public readonly Money $spent,
        public readonly Money $cap,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->channel->label()} spend is nearing this month's limit — Baseline Lodge")
            ->line("{$this->channel->label()} messages this month: {$this->spent->format()} of the {$this->cap->format()} limit.")
            ->line('When the limit is reached, only critical messages (such as payment confirmations) are sent.')
            ->action('Review messages', url('/admin/messages'));
    }
}
