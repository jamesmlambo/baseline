<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\StaffRole;
use App\Mail\DailyReportMail;
use App\Models\DailyReport;
use App\Models\ReportDelivery;
use App\Models\Setting;
use App\Models\User;
use App\Services\Notifications\PushNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

/**
 * The 17:00 owner report (REP-03, DEC-10) and its archive (REP-04).
 *
 * - run(): once the configured time has passed, store today's snapshot (once) and deliver it.
 * - Every email and push is a delivery row; failures are retried up to 5 times with a growing gap.
 * - freeze(): the optional day-close freeze of reconciled money totals, separate from the snapshot.
 * Urgent guest escalations run on their own schedule and never wait for this (REP-05).
 */
class DailyReports
{
    public function __construct(
        private readonly ReportMetrics $metrics,
        private readonly PushNotifier $push,
    ) {}

    /**
     * Safe to call every minute. Returns the report when one was generated or delivered now.
     */
    public function run(?CarbonImmutable $now = null): ?DailyReport
    {
        $now = ($now ?? CarbonImmutable::now())->setTimezone('Africa/Johannesburg');
        $time = (string) Setting::get('reports.daily_time', '17:00');

        if ($now->format('H:i') < substr($time, 0, 5)) {
            return null;
        }

        $report = DailyReport::query()->whereDate('report_date', $now->toDateString())->first() ?? $this->generate($now);

        if ($report->deliveries()->exists()) {
            return null;
        }

        $this->deliver($report);

        return $report;
    }

    public function generate(CarbonImmutable $day): DailyReport
    {
        $day = CarbonImmutable::parse($day->toDateString(), 'Africa/Johannesburg');

        $data = $this->metrics->forPeriod($day, $day);
        $monthToDate = $this->metrics->forPeriod($day->startOfMonth(), $day);
        $data['month_to_date'] = ['rooms' => $monthToDate['rooms'], 'money' => $monthToDate['money'], 'stays' => $monthToDate['stays']];
        $data['tomorrow'] = ['arrivals' => $this->metrics->forPeriod($day->addDay(), $day->addDay())['operations']['arrivals']];

        try {
            return DailyReport::query()->create([
                'report_date' => $day->toDateString(),
                'data' => $data,
                'generated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }

            return DailyReport::query()->whereDate('report_date', $day->toDateString())->firstOrFail();
        }
    }

    /**
     * DEC-10: email + push to every active owner. Existing deliveries are not repeated.
     */
    public function deliver(DailyReport $report): void
    {
        $owners = User::query()->role(StaffRole::Owner->value)->where('is_active', true)->get();

        foreach ($owners as $owner) {
            foreach (['email', 'push'] as $channel) {
                $delivery = ReportDelivery::query()->firstOrCreate(
                    ['daily_report_id' => $report->id, 'user_id' => $owner->id, 'channel' => $channel],
                    ['status' => 'pending'],
                );

                if ($delivery->wasRecentlyCreated) {
                    $this->attempt($delivery);
                }
            }
        }
    }

    /**
     * Retry failed deliveries: after 5, 10, 20, 40 minutes, then give up (the dashboard shows it).
     */
    public function retryFailed(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $retried = 0;

        ReportDelivery::query()
            ->where('status', 'failed')
            ->where('attempts', '<', ReportDelivery::MAX_ATTEMPTS)
            ->get()
            ->each(function (ReportDelivery $delivery) use ($now, &$retried): void {
                $wait = 5 * (2 ** max(0, $delivery->attempts - 1));

                if ($delivery->updated_at->addMinutes($wait)->greaterThan($now)) {
                    return;
                }

                $this->attempt($delivery);
                $retried++;
            });

        return $retried;
    }

    public function attempt(ReportDelivery $delivery): ReportDelivery
    {
        $delivery->loadMissing(['report', 'user']);
        $url = url('/admin/daily-reports/'.$delivery->report->id);
        $delivery->attempts++;

        try {
            if ($delivery->channel === 'email') {
                Mail::to($delivery->user)->send(new DailyReportMail($delivery->report, $url));
                $delivery->forceFill(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
            } elseif (! $this->push->isLive()) {
                $this->push->send($delivery->user, 'Daily report ready', $delivery->report->report_date->format('j M Y'), $url);
                $delivery->forceFill(['status' => 'skipped', 'last_error' => 'Web push is not connected yet (log driver).']);
            } else {
                $this->push->send($delivery->user, 'Daily report ready', $delivery->report->report_date->format('j M Y'), $url);
                $delivery->forceFill(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
            }
        } catch (Throwable $exception) {
            $delivery->forceFill(['status' => 'failed', 'last_error' => mb_substr($exception->getMessage(), 0, 1000)]);
        }

        $delivery->save();

        return $delivery;
    }

    /**
     * REP-04: freeze the day's money totals once reconciled. Owner or manager; only once; audited.
     */
    public function freeze(DailyReport $report, User $by): DailyReport
    {
        if (! $by->is_active || ! $by->hasAnyRole([StaffRole::Owner->value, StaffRole::Manager->value])) {
            throw new AuthorizationException('Only the owner or a manager can close a day.');
        }

        if ($report->report_date->isFuture() || $report->report_date->isToday() && CarbonImmutable::now('Africa/Johannesburg')->format('H') < '17') {
            throw new InvalidArgumentException('A day can be closed from the evening of that day.');
        }

        return DB::transaction(function () use ($report, $by): DailyReport {
            /** @var DailyReport $locked */
            $locked = DailyReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            if ($locked->isFrozen()) {
                throw new InvalidArgumentException('This day is already closed.');
            }

            $day = CarbonImmutable::parse($locked->report_date->toDateString(), 'Africa/Johannesburg');
            $totals = $this->metrics->forPeriod($day, $day)['money'];

            $locked->forceFill(['frozen_totals' => $totals, 'frozen_at' => now(), 'frozen_by' => $by->id])->save();

            activity('reports')->performedOn($locked)->causedBy($by)->event('day_closed')
                ->withProperties(['date' => $day->toDateString(), 'totals' => $totals])
                ->log("Day closed: {$day->format('j M Y')}");

            return $locked;
        });
    }
}
