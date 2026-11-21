<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds system settings. Existing values are never overwritten, so this is
 * safe to re-run in every environment. PENDING items follow
 * FINAL_SPECIFICATION.md §3.2 and must not be treated as approved policy.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            Setting::query()->firstOrCreate(['key' => $definition['key']], $definition);
        }
    }

    /**
     * @return array<int, array{group: string, key: string, label: string, value: mixed, type: string, is_pending_client_decision: bool, decision_reference: ?string, help_text: ?string}>
     */
    private function definitions(): array
    {
        return [
            $this->setting('general', 'general.site_name', 'Site name', 'Baseline Lodge', 'string'),
            $this->setting('general', 'general.timezone', 'Business timezone', 'Africa/Johannesburg', 'string'),
            $this->setting('general', 'general.currency', 'Currency', 'ZAR', 'string'),
            $this->setting('contact', 'contact.phone', 'Public phone number', null, 'string', 'PEND-04', 'Real contact details not yet supplied. Do not invent them.'),
            $this->setting('contact', 'contact.email', 'Public email address', null, 'string', 'PEND-04', 'Real contact details not yet supplied.'),
            $this->setting('contact', 'contact.whatsapp', 'WhatsApp number', null, 'string', 'PEND-04', 'Used for the click-to-chat button.'),
            $this->setting('contact', 'contact.address', 'Physical address', null, 'text', 'PEND-04', 'Real address not yet supplied.'),
            $this->setting('booking', 'booking.hold_minutes_online', 'Online / WhatsApp hold duration (minutes)', 30, 'integer', 'PEND-01', 'Development default only. Reviewers proposed 2 hours; the client has not decided.'),
            $this->setting('booking', 'booking.hold_hours_manual_eft', 'Manual EFT hold duration (hours)', null, 'integer', 'PEND-01', 'Staff choose an expiry for each manual hold until this is decided. Reviewers proposed 12–24 hours.'),
            $this->setting('booking', 'booking.check_in_time', 'Check-in time', null, 'time', 'PEND-05'),
            $this->setting('booking', 'register.required_fields', 'Guest register: required fields', ['full_name'], 'list', 'VERIFY', 'Which fields the law requires (e.g. ID number, nationality) must be confirmed by a legal adviser. Options: full_name, id_type, id_number, nationality, home_address, vehicle_registration, number_of_guests.'),
            $this->setting('booking', 'booking.check_out_time', 'Check-out time', null, 'time', 'PEND-05'),
            $this->setting('payments', 'payments.method_order', 'Payment method display order', ['instant_eft', 'card', 'eft'], 'list', 'PEND-03', 'Instant EFT first is a client decision (DEC-05). The order of card and ordinary EFT is not yet decided.'),
            $this->setting('payments', 'payments.deposit_percent', 'Payment needed to confirm a booking (% of total)', 100, 'integer', 'PEND-05', 'Development default: full payment confirms the booking. Deposit policy not yet decided.'),
            $this->setting('payments', 'payments.bank_name', 'Bank name (for EFT)', null, 'string', 'PEND-04', 'Shown on invoices and EFT instructions. Never invent banking details.'),
            $this->setting('payments', 'payments.bank_account_name', 'Bank account name', null, 'string', 'PEND-04'),
            $this->setting('payments', 'payments.bank_account_number', 'Bank account number', null, 'string', 'PEND-04'),
            $this->setting('payments', 'payments.bank_branch_code', 'Bank branch code', null, 'string', 'PEND-04'),
            $this->setting('payments', 'payments.vat_number', 'VAT number', null, 'string', 'VERIFY', 'Only if the business is VAT-registered. Leave empty otherwise.'),
            $this->setting('payments', 'payments.vat_rate_percent', 'VAT rate added to invoices (%)', null, 'integer', 'VERIFY', 'Leave empty unless a tax adviser confirms VAT applies (COMPLIANCE_TODO).'),
            $this->setting('payments', 'payments.invoice_due_days', 'Invoice due after (days)', 0, 'integer', 'PEND-05', 'Development default: due immediately.'),
            $this->setting('messaging', 'messaging.preferred_channel', 'Send automatic messages by (whatsapp or sms)', 'whatsapp', 'string', 'PEND-07', 'Development default. Depends on the providers chosen.'),
            $this->setting('messaging', 'messaging.feedback_request_enabled', 'Send a feedback request the day after checkout (yes/no)', 'no', 'string', 'PEND-14', 'Optional automation; off until the client approves it.'),
            $this->setting('messaging', 'price_lists.valid_hours', 'WhatsApp price-list links work for (hours)', 24, 'integer', 'PEND-01', 'Development default. A link never holds a room.'),
            $this->setting('messaging', 'price_lists.follow_up_hours', 'Follow up an unused price list after (hours, 0 = never)', 4, 'integer', 'PEND-12', 'Development default.'),
            $this->setting('messaging', 'messaging.monthly_cap_sms_cents', 'Monthly SMS spend cap (cents)', null, 'integer', 'PEND-07', 'Set once an SMS provider is chosen.'),
            $this->setting('messaging', 'messaging.monthly_cap_whatsapp_cents', 'Monthly WhatsApp spend cap (cents)', null, 'integer', 'PEND-07', 'Set once a WhatsApp provider is chosen.'),
            $this->setting('escalation', 'escalation.backup_minutes', 'Escalate unanswered guest request to backup after (minutes)', 10, 'integer', 'PEND-12', 'Recommended default.'),
            $this->setting('escalation', 'escalation.owner_minutes', 'Escalate unanswered guest request to owner after (minutes)', 30, 'integer', 'PEND-12', 'Recommended default.'),
            $this->setting('housekeeping', 'inspections.scale_max', 'Inspection score scale: highest score', 5, 'integer', 'PEND-15', 'Development default. Each checklist item is scored from 0 to this number.'),
            $this->setting('housekeeping', 'inspections.pass_mark', 'Inspection pass mark (each item)', 4, 'integer', 'PEND-15', 'Development default. Any item scored below this fails the inspection and creates rework.'),
            $this->setting('features', 'features.indemnity', 'Ask for a safety / indemnity acknowledgement on services that need one (yes/no)', 'no', 'string', 'PEND-14', 'Off until the client approves the wording (legal review needed).'),
            $this->setting('features', 'features.vouchers', 'Vouchers (yes/no)', 'no', 'string', 'PEND-14', 'Not built yet. Optional extra.'),
            $this->setting('features', 'features.packages', 'Packages (yes/no)', 'no', 'string', 'PEND-14', 'Not built yet. Optional extra.'),
            $this->setting('features', 'features.event_hire', 'Event hire (yes/no)', 'no', 'string', 'PEND-14', 'Not built yet. Optional extra.'),
            $this->setting('channels', 'channels.ical_fetch_enabled', 'Fetch channel calendars (iCal) automatically (yes/no)', 'no', 'string', 'PEND-07', 'Off until approved: the server would download calendars from the channel. Until then, paste or upload the calendar file.'),
            $this->setting('privacy', 'retention.guest_profile_months', 'Anonymise guest profiles this many months after their last stay', null, 'integer', 'PEND-11', 'Empty = keep. Must be confirmed with a legal adviser (POPIA, guest register, tax).'),
            $this->setting('privacy', 'retention.message_months', 'Remove SMS/WhatsApp message text after (months)', null, 'integer', 'PEND-11', 'Empty = keep. The delivery record is kept.'),
            $this->setting('privacy', 'retention.portal_code_months', 'Delete guest-portal sign-in codes after (months)', null, 'integer', 'PEND-11', 'Empty = keep. Codes are stored as hashes only.'),
            $this->setting('privacy', 'retention.enforcement_enabled', 'Apply the retention periods automatically (yes/no)', 'no', 'string', 'PEND-11', 'Off: nothing is changed or deleted until the client approves the periods. Run "php artisan privacy:retention" to see the effect first.'),
            $this->setting('reports', 'reports.daily_time', 'Daily owner report time', '17:00', 'time'),
            $this->setting('portal', 'portal.welcome', 'Guest portal: welcome message', null, 'text', 'PEND-04', 'Shown at the top of the guest portal.'),
            $this->setting('portal', 'portal.wifi_name', 'Guest portal: Wi-Fi network name', null, 'string', 'PEND-04'),
            $this->setting('portal', 'portal.wifi_password', 'Guest portal: Wi-Fi password', null, 'string', 'PEND-04', 'Only shown to signed-in guests.'),
            $this->setting('portal', 'portal.facility_hours', 'Guest portal: facility hours', null, 'text', 'PEND-04', 'e.g. pool, spa, breakfast times.'),
            $this->setting('portal', 'portal.emergency_contacts', 'Guest portal: emergency contacts', null, 'text', 'PEND-04', 'Night duty number, nearest hospital, etc. Must be supplied by the lodge.'),
        ];
    }

    /**
     * @return array{group: string, key: string, label: string, value: mixed, type: string, is_pending_client_decision: bool, decision_reference: ?string, help_text: ?string}
     */
    private function setting(string $group, string $key, string $label, mixed $value, string $type, ?string $decisionReference = null, ?string $helpText = null): array
    {
        return [
            'group' => $group,
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'type' => $type,
            'is_pending_client_decision' => $decisionReference !== null,
            'decision_reference' => $decisionReference,
            'help_text' => $helpText,
        ];
    }
}
