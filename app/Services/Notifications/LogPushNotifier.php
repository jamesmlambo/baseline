<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Writes pushes to the log instead of sending them. Deliveries through it are recorded as
 * "skipped", never as sent.
 */
class LogPushNotifier implements PushNotifier
{
    public function isLive(): bool
    {
        return false;
    }

    public function send(User $user, string $title, string $body, string $url): void
    {
        Log::info("[Push NOT SENT — log driver] to user #{$user->id}: {$title} — {$body} ({$url})");
    }
}
