<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A short operational notice to a staff member: a task was assigned, rework is needed, stock is
 * low, a fault was reported. No guest personal data; it links to the right screen.
 */
class StaffAlert extends Notification
{
    public function __construct(
        public readonly string $subject,
        public readonly string $line,
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
            ->subject($this->subject)
            ->line($this->line)
            ->action('Open', $this->url);
    }
}
