<?php

declare(strict_types=1);

use App\Actions\Bookings\ReleaseExpiredHolds;
use App\Actions\Messaging\RunMessageAutomations;
use App\Actions\Portal\EscalateGuestRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('bookings:release-expired-holds', function (ReleaseExpiredHolds $action): void {
    $count = $action->handle();
    $this->info("Released {$count} expired hold(s).");
})->purpose('Expire booking holds past their expiry time and free their rooms (BOOK-04)');

Artisan::command('messages:run-automations', function (RunMessageAutomations $action): void {
    foreach ($action->handle() as $type => $count) {
        $this->line(str_pad($type, 24).$count);
    }
})->purpose('Send time-based guest messages: hold reminders, pre-arrival, departure day, follow-ups (MSG-03)');

Artisan::command('portal:escalate', function (EscalateGuestRequests $action): void {
    $counts = $action->handle();
    $this->info("Escalated to managers: {$counts['managers']}, to owner: {$counts['owner']}.");
})->purpose('Escalate unanswered guest requests and messages (GST-08)');

Schedule::command('portal:escalate')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('bookings:release-expired-holds')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('messages:run-automations')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();
