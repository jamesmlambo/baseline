<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\Setting;
use App\Support\Money;

/**
 * Monthly spend cap per channel (MSG-06): owner alert at 80%, and at 100% only critical
 * messages (e.g. payment confirmations, guest-portal codes) still go out.
 */
class SpendGuard
{
    public function cap(MessageChannel $channel): ?Money
    {
        $cap = Setting::get($channel->capSettingKey());

        return is_numeric($cap) && (int) $cap > 0 ? Money::cents((int) $cap) : null;
    }

    public function spentThisMonth(MessageChannel $channel): Money
    {
        return Money::cents((int) Message::query()
            ->where('channel', $channel)
            ->whereIn('status', [MessageStatus::Queued, MessageStatus::Sent, MessageStatus::Delivered])
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('cost_cents'));
    }

    public function percentUsed(MessageChannel $channel): ?int
    {
        $cap = $this->cap($channel);

        return $cap === null ? null : intdiv($this->spentThisMonth($channel)->cents * 100, $cap->cents);
    }

    /**
     * Would one more message of this estimated cost go over the cap?
     */
    public function blocks(MessageChannel $channel, Money $estimate, bool $isCritical): bool
    {
        $cap = $this->cap($channel);

        if ($cap === null || $isCritical) {
            return false;
        }

        return $this->spentThisMonth($channel)->add($estimate)->cents > $cap->cents;
    }

    public function shouldAlert(MessageChannel $channel): bool
    {
        $percent = $this->percentUsed($channel);

        return $percent !== null && $percent >= (int) config('messaging.cap_alert_percent', 80);
    }
}
