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
            $this->setting('booking', 'booking.check_out_time', 'Check-out time', null, 'time', 'PEND-05'),
            $this->setting('payments', 'payments.method_order', 'Payment method display order', ['instant_eft', 'card', 'eft'], 'list', 'PEND-03', 'Instant EFT first is a client decision (DEC-05). The order of card and ordinary EFT is not yet decided.'),
            $this->setting('messaging', 'messaging.monthly_cap_sms_cents', 'Monthly SMS spend cap (cents)', null, 'integer', 'PEND-07', 'Set once an SMS provider is chosen.'),
            $this->setting('messaging', 'messaging.monthly_cap_whatsapp_cents', 'Monthly WhatsApp spend cap (cents)', null, 'integer', 'PEND-07', 'Set once a WhatsApp provider is chosen.'),
            $this->setting('escalation', 'escalation.backup_minutes', 'Escalate unanswered guest request to backup after (minutes)', 10, 'integer', 'PEND-12', 'Recommended default.'),
            $this->setting('escalation', 'escalation.owner_minutes', 'Escalate unanswered guest request to owner after (minutes)', 30, 'integer', 'PEND-12', 'Recommended default.'),
            $this->setting('reports', 'reports.daily_time', 'Daily owner report time', '17:00', 'time'),
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
