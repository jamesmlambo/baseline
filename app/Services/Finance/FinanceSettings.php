<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Setting;
use App\Support\Money;

/**
 * Finance settings in one place. Nothing here is invented: unset banking details stay null
 * and VAT is only added when a rate has been configured.
 */
class FinanceSettings
{
    public function depositPercent(): int
    {
        return max(0, min(100, (int) Setting::get('payments.deposit_percent', 100)));
    }

    public function depositFor(Money $total): Money
    {
        return Money::cents(intdiv($total->cents * $this->depositPercent() + 99, 100));
    }

    public function vatRatePercent(): ?int
    {
        $rate = Setting::get('payments.vat_rate_percent');

        return is_numeric($rate) && (int) $rate > 0 ? (int) $rate : null;
    }

    public function vatNumber(): ?string
    {
        return Setting::get('payments.vat_number');
    }

    public function invoiceDueDays(): int
    {
        return max(0, (int) Setting::get('payments.invoice_due_days', 0));
    }

    /**
     * @return array{bank: ?string, account_name: ?string, account_number: ?string, branch_code: ?string}|null
     */
    public function bankDetails(): ?array
    {
        $details = [
            'bank' => Setting::get('payments.bank_name'),
            'account_name' => Setting::get('payments.bank_account_name'),
            'account_number' => Setting::get('payments.bank_account_number'),
            'branch_code' => Setting::get('payments.bank_branch_code'),
        ];

        return $details['account_number'] !== null ? $details : null;
    }

    public function eftHoldHours(): ?int
    {
        $hours = Setting::get('booking.hold_hours_manual_eft');

        return is_numeric($hours) && (int) $hours > 0 ? (int) $hours : null;
    }

    public function onlineHoldMinutes(): int
    {
        return max(1, (int) Setting::get('booking.hold_minutes_online', 30));
    }
}
