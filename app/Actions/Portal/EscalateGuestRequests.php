<?php

declare(strict_types=1);

namespace App\Actions\Portal;

use App\Enums\GuestRequestStatus;
use App\Enums\StaffRole;
use App\Models\GuestMessage;
use App\Models\GuestRequest;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\GuestRequestEscalated;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * GST-08 / REP-05: unacknowledged guest requests and unread guest messages escalate to managers,
 * then to the owner, on the timers in Settings (PEND-12). Runs every minute; each level fires once.
 */
class EscalateGuestRequests
{
    /**
     * @return array{managers: int, owner: int}
     */
    public function handle(): array
    {
        $backupMinutes = max(1, (int) Setting::get('escalation.backup_minutes', 10));
        $ownerMinutes = max($backupMinutes, (int) Setting::get('escalation.owner_minutes', 30));
        $counts = ['managers' => 0, 'owner' => 0];

        // Managers first, then the owner, so a long-waiting request reaches both.
        foreach ([[1, $backupMinutes, StaffRole::Manager, 'managers'], [2, $ownerMinutes, StaffRole::Owner, 'owner']] as [$level, $minutes, $role, $key]) {
            $recipients = $this->staff($role);

            GuestRequest::query()
                ->with('room')
                ->where('status', GuestRequestStatus::New)
                ->where('escalation_level', '<', $level)
                ->where('created_at', '<=', now()->subMinutes($minutes))
                ->each(function (GuestRequest $request) use ($recipients, $level, &$counts, $key): void {
                    Notification::send($recipients, new GuestRequestEscalated(
                        "{$request->type->label()} request".($request->room ? " from {$request->room->name}" : ''),
                        (int) $request->created_at->diffInMinutes(now()),
                        url('/admin/guest-requests'),
                    ));
                    $request->forceFill(['escalation_level' => $level])->save();
                    $counts[$key]++;
                });

            GuestMessage::query()
                ->where('direction', GuestMessage::IN)
                ->whereNull('read_at')
                ->where('escalation_level', '<', $level)
                ->where('created_at', '<=', now()->subMinutes($minutes))
                ->each(function (GuestMessage $message) use ($recipients, $level, &$counts, $key): void {
                    Notification::send($recipients, new GuestRequestEscalated(
                        'Guest message',
                        (int) $message->created_at->diffInMinutes(now()),
                        url('/admin/guest-messages'),
                    ));
                    $message->forceFill(['escalation_level' => $level])->save();
                    $counts[$key]++;
                });
        }

        return $counts;
    }

    /**
     * @return Collection<int, User>
     */
    private function staff(StaffRole $role): Collection
    {
        $users = User::query()->role($role->value)->where('is_active', true)->get();

        // No manager? The owner hears about it at the first level instead.
        return $users->isEmpty() && $role === StaffRole::Manager ? $this->staff(StaffRole::Owner) : $users;
    }
}
