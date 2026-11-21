<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\User;

/**
 * Web push to staff browsers (REP-03, DEC-10). Only a log driver exists: a real web-push
 * channel needs VAPID keys and a new package, which wait for approval (docs/INTEGRATIONS.md).
 */
interface PushNotifier
{
    /**
     * Whether pushes really leave the server. The log driver returns false.
     */
    public function isLive(): bool;

    /**
     * @throws \RuntimeException when the push service rejects or cannot be reached
     */
    public function send(User $user, string $title, string $body, string $url): void;
}
