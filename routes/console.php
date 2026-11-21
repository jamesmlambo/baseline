<?php

declare(strict_types=1);

use App\Actions\Bookings\ReleaseExpiredHolds;
use App\Actions\Housekeeping\GenerateDeepCleans;
use App\Actions\Housekeeping\RunMaintenanceSchedules;
use App\Actions\Messaging\RunMessageAutomations;
use App\Actions\Portal\EscalateGuestRequests;
use App\Services\Channels\ChannelSync;
use App\Services\Operations\ProductionReadiness;
use App\Services\Privacy\RetentionReview;
use App\Services\Reports\DailyReports;
use App\Services\Staff\Attendance\AttendanceCalculator;
use Carbon\CarbonImmutable;
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

Artisan::command('housekeeping:deep-cleans', function (GenerateDeepCleans $action): void {
    $this->info("Created {$action->handle()} Monday deep-clean task(s).");
})->purpose('Create the Monday deep-clean task for every active room (HK-04)');

Artisan::command('maintenance:run-schedules', function (RunMaintenanceSchedules $action): void {
    $this->info("Created {$action->handle()} preventive maintenance task(s).");
})->purpose('Create tasks for preventive maintenance that is due (HK-08)');

Artisan::command('attendance:derive {--date= : Work date (YYYY-MM-DD); default yesterday and today}', function (AttendanceCalculator $calculator): void {
    $dates = $this->option('date')
        ? [CarbonImmutable::parse((string) $this->option('date'), 'Africa/Johannesburg')]
        : [CarbonImmutable::yesterday('Africa/Johannesburg'), CarbonImmutable::today('Africa/Johannesburg')];

    foreach ($dates as $date) {
        $this->line($date->toDateString().': '.$calculator->deriveDate($date).' day(s) updated');
    }
})->purpose('Work out attendance from rosters and punches (ATT-03)');

// PRIV-05 backups: database every 4 hours (RPO 4 h), database + uploads nightly, then clean-up and a health check.
Schedule::command('backup:run --only-db')->cron('0 */4 * * *')->timezone('Africa/Johannesburg')->withoutOverlapping()->onOneServer();
Schedule::command('backup:run')->dailyAt('01:30')->timezone('Africa/Johannesburg')->withoutOverlapping()->onOneServer();
Schedule::command('backup:clean')->dailyAt('03:00')->timezone('Africa/Johannesburg')->onOneServer();
Schedule::command('backup:monitor')->dailyAt('07:00')->timezone('Africa/Johannesburg')->onOneServer();

Artisan::command('app:production-check', function (ProductionReadiness $readiness): int {
    $results = $readiness->check();

    $this->table(['', 'Check', 'Result'], array_map(fn (array $row): array => [
        match ($row['status']) {
            'ok' => 'OK',
            'warn' => 'CHECK',
            default => 'FAIL',
        },
        $row['area'],
        $row['message'],
    ], $results));

    $failures = count(array_filter($results, fn (array $row): bool => $row['status'] === 'fail'));
    $failures > 0 ? $this->error("{$failures} item(s) block go-live. See docs/PRODUCTION_CHECKLIST.md.") : $this->info('No blocking items.');

    return $failures > 0 ? 1 : 0;
})->purpose('List what still blocks going live: demo data and accounts, fake drivers, pending decisions, backups');

Artisan::command('privacy:retention {--apply : Apply the rules (only works when retention.enforcement_enabled is yes)}', function (RetentionReview $retention): void {
    $this->table(['Data', 'Keep (months)', 'Would be affected', 'Action'], collect($retention->review())->map(fn (array $rule, string $name): array => [
        $name, $rule['months'] ?? 'keep (not decided)', $rule['affected'], $rule['action'],
    ])->values()->all());

    if (! $this->option('apply')) {
        $this->info('Review only. Nothing was changed.');

        return;
    }

    if (! RetentionReview::enforcementEnabled()) {
        $this->warn('Retention enforcement is switched off (PEND-11). Nothing was changed.');

        return;
    }

    $this->info('Applied: '.json_encode($retention->enforce()));
})->purpose('Show (and, once approved, apply) data retention rules (PRIV-03)');

// Runs daily but changes nothing until the client approves the periods (PEND-11).
Schedule::call(fn () => app(RetentionReview::class)->enforce())
    ->name('privacy:retention-enforce')
    ->dailyAt('02:30')
    ->timezone('Africa/Johannesburg')
    ->onOneServer();

Artisan::command('channels:sync', function (ChannelSync $sync): void {
    $this->info("Checked {$sync->syncAll()} enabled channel(s). See Channels for each run's result.");
})->purpose('Sync enabled channels and record each attempt (CHN-01/04)');

// Scheduled reconciliation (CHN-04). With no channel manager connected each run is logged as "not connected".
Schedule::command('channels:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Artisan::command('reports:daily', function (DailyReports $reports): void {
    $report = $reports->run();
    $retried = $reports->retryFailed();
    $this->info(($report ? "Daily report for {$report->report_date->toDateString()} sent." : 'No report due.')." Retried {$retried} failed delivery(ies).");
})->purpose('Generate and deliver the daily owner report after the configured time, and retry failed deliveries (REP-03)');

// Checked every minute so a missed 17:00 run catches up; it generates and sends once per day.
Schedule::command('reports:daily')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('attendance:derive')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('housekeeping:deep-cleans')
    ->mondays()
    ->at('06:00')
    ->timezone('Africa/Johannesburg')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('maintenance:run-schedules')
    ->dailyAt('06:05')
    ->timezone('Africa/Johannesburg')
    ->withoutOverlapping()
    ->onOneServer();
