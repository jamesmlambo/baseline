<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Services\Staff\Roster;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A short operational notice to a staff member: a task was assigned, rework is needed, stock is
 * low, a fault was reported. No guest personal data; it links to the right screen.
 *
 * EMP-04: non-urgent notices are not sent to staff who are off duty. The work itself stays in
 * their task list for their next shift.
 */
class StaffAlert extends Notification
{
    public function __construct(
        public readonly string $subject,
        public readonly string $line,
        public readonly string $url,
        public readonly bool $urgent = false,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if (! $this->urgent && $notifiable instanceof User && app(Roster::class)->isOffDuty($notifiable)) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(($this->urgent ? 'URGENT: ' : '').$this->subject)
            ->line($this->line)
            ->action('Open', $this->url);
    }
}
