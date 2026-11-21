<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * GST-08: a guest has been waiting without a reply. No guest details in the email: it links to admin.
 */
class GuestRequestEscalated extends Notification
{
    public function __construct(
        public readonly string $what,
        public readonly int $minutesWaiting,
        public readonly string $url,
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
            ->subject("A guest is waiting — {$this->what} ({$this->minutesWaiting} min)")
            ->line("{$this->what} has not been answered for {$this->minutesWaiting} minutes.")
            ->action('Open in admin', $this->url);
    }
}
