<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\EnquiryType;
use App\Models\Enquiry;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the front desk a new enquiry has arrived. Personal details stay in the admin area,
 * so the email only links to it (REP-03 principle: no unnecessary personal data in email).
 */
class NewWebsiteEnquiry extends Notification
{
    public function __construct(public readonly Enquiry $enquiry) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $kind = $this->enquiry->type === EnquiryType::BookingRequest ? 'booking request' : 'message';

        return (new MailMessage)
            ->subject("New website {$kind} — Baseline Lodge")
            ->line("A new website {$kind} has arrived.")
            ->action('Open in admin', url('/admin/enquiries/'.$this->enquiry->id))
            ->line('Please reply within the same working day.');
    }
}
