<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\BookingStatus;
use App\Enums\EmployeeStatus;
use App\Enums\GuestRequestStatus;
use App\Enums\InspectionStatus;
use App\Enums\MessageStatus;
use App\Enums\PaymentRecordStatus;
use App\Enums\RefundStatus;
use App\Enums\RoomCleaningStatus;
use App\Enums\ServiceBookingStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The owner's numbers (REP-01/02/06). Every figure says which date it is based on:
 *
 * - "stay date": the night or day the guest is with us (occupancy, ADR, RevPAR, stays by status).
 * - "transaction date": when money moved or a document was issued (payments, refunds, invoices).
 * - "created date": when a booking or enquiry was made (sources, conversion, campaigns).
 * - "as at": the position when the report was generated (balances, open work).
 *
 * Formulas are in docs/REPORT_METRICS.md. Money is integer cents. No industry benchmarks are shown.
 */
class ReportMetrics
{
    /**
     * Bookings that count as sold: confirmed, in house or checked out.
     *
     * @var list<string>
     */
    public const SOLD = ['confirmed', 'checked_in', 'checked_out'];

    /**
     * @return array<string, mixed>
     */
    public function forPeriod(CarbonImmutable $from, CarbonImmutable $until, ?CarbonImmutable $asAt = null): array
    {
        $from = CarbonImmutable::parse($from->toDateString(), 'Africa/Johannesburg');
        $until = CarbonImmutable::parse($until->toDateString(), 'Africa/Johannesburg');
        $asAt ??= CarbonImmutable::now();

        return [
            'period' => [
                'from' => $from->toDateString(),
                'until' => $until->toDateString(),
                'nights' => (int) $from->diffInDays($until) + 1,
                'as_at' => $asAt->toIso8601String(),
            ],
            'rooms' => $this->rooms($from, $until),
            'stays' => $this->staysByStatus($from, $until),
            'sources' => $this->sources($from, $until),
            'money' => $this->money($from, $until),
            'balances' => $this->balances(),
            'operations' => $this->operations($from, $until, $asAt),
            'marketing' => $this->marketing($from, $until),
        ];
    }

    /**
     * Stay-date basis.
     *
     * @return array<string, int|float|null>
     */
    private function rooms(CarbonImmutable $from, CarbonImmutable $until): array
    {
        $nights = (int) $from->diffInDays($until) + 1;
        $activeRooms = Room::query()->active()->count();

        $blocked = (int) DB::table('room_nights')
            ->whereNotNull('room_block_id')
            ->whereNull('released_at')
            ->whereBetween('night_date', [$from->toDateString(), $until->toDateString()])
            ->count();

        $sold = DB::table('room_nights')
            ->join('booking_rooms', 'booking_rooms.id', '=', 'room_nights.booking_room_id')
            ->join('bookings', 'bookings.id', '=', 'booking_rooms.booking_id')
            ->whereNull('room_nights.released_at')
            ->whereIn('bookings.status', self::SOLD)
            ->whereBetween('room_nights.night_date', [$from->toDateString(), $until->toDateString()])
            ->selectRaw('count(*) as nights_sold, coalesce(sum(booking_rooms.accommodation_total_cents::numeric / greatest(booking_rooms.departure_date - booking_rooms.arrival_date, 1)), 0) as revenue')
            ->first();

        $available = max(0, $activeRooms * $nights - $blocked);
        $nightsSold = (int) $sold->nights_sold;
        $revenue = (int) round((float) $sold->revenue);

        return [
            'rooms_available' => $available,
            'nights_blocked' => $blocked,
            'nights_sold' => $nightsSold,
            'occupancy_percent' => $available > 0 ? round($nightsSold / $available * 100, 1) : null,
            'room_revenue_cents' => $revenue,
            'adr_cents' => $nightsSold > 0 ? (int) round($revenue / $nightsSold) : null,
            'revpar_cents' => $available > 0 ? (int) round($revenue / $available) : null,
        ];
    }

    /**
     * Stay-date basis: bookings whose stay overlaps the period, kept apart by status (REP-02).
     *
     * @return array<string, int>
     */
    private function staysByStatus(CarbonImmutable $from, CarbonImmutable $until): array
    {
        $counts = Booking::query()
            ->where('arrival_date', '<=', $until->toDateString())
            ->where('departure_date', '>', $from->toDateString())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        $sum = fn (array $statuses): int => (int) collect($statuses)->sum(fn (string $status): int => (int) ($counts[$status] ?? 0));

        return [
            'confirmed' => $sum(self::SOLD),
            'pending' => $sum(['quote', 'hold', 'pending']),
            'cancelled' => $sum(['cancelled']),
            'no_show' => $sum(['no_show']),
            'expired' => $sum(['expired']),
        ];
    }

    /**
     * Created-date basis.
     *
     * @return array<string, array{made: int, confirmed: int}>
     */
    private function sources(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return Booking::query()
            ->whereBetween('created_at', [$from->startOfDay(), $until->endOfDay()])
            ->selectRaw("source, count(*) as made, count(*) filter (where status in ('confirmed', 'checked_in', 'checked_out')) as confirmed")
            ->groupBy('source')
            ->orderByDesc('made')
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($row): array => [$row->source => ['made' => (int) $row->made, 'confirmed' => (int) $row->confirmed]])
            ->all();
    }

    /**
     * Transaction-date basis.
     *
     * @return array<string, mixed>
     */
    private function money(CarbonImmutable $from, CarbonImmutable $until): array
    {
        $range = [$from->startOfDay(), $until->endOfDay()];

        $byMethod = DB::table('payments')
            ->where('status', PaymentRecordStatus::Succeeded->value)
            ->whereBetween('received_at', $range)
            ->selectRaw('method, coalesce(sum(amount_cents), 0) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->map(fn ($total): int => (int) $total)
            ->all();

        return [
            'payments_received_cents' => array_sum($byMethod),
            'payments_by_method_cents' => $byMethod,
            'refunds_paid_cents' => (int) DB::table('refunds')->where('status', RefundStatus::Processed->value)->whereBetween('processed_at', $range)->sum('amount_cents'),
            'invoiced_cents' => (int) DB::table('invoices')->whereNull('voided_at')->whereBetween('issued_at', $range)->sum('total_cents'),
            'credit_notes_cents' => (int) DB::table('credit_notes')->whereBetween('issued_at', $range)->sum('total_cents'),
            'leisure_completed_cents' => (int) DB::table('service_bookings')->where('status', ServiceBookingStatus::Completed->value)->whereBetween('completed_at', $range)->sum('total_cents'),
            'eft_awaiting_verification' => DB::table('payments')->where('status', PaymentRecordStatus::PendingVerification->value)->count(),
        ];
    }

    /**
     * As-at basis: money still owed by guests in house or who left in the last 90 days.
     *
     * @return array{count: int, owed_cents: int}
     */
    private function balances(): array
    {
        $owing = Booking::query()
            ->where(fn ($query) => $query->where('status', BookingStatus::CheckedIn)
                ->orWhere(fn ($inner) => $inner->where('status', BookingStatus::CheckedOut)->where('departure_date', '>=', today()->subDays(90)->toDateString())))
            ->get()
            ->map(fn (Booking $booking): int => $booking->balanceDue()->cents)
            ->filter(fn (int $cents): bool => $cents > 0);

        return ['count' => $owing->count(), 'owed_cents' => (int) $owing->sum()];
    }

    /**
     * @return array<string, mixed>
     */
    private function operations(CarbonImmutable $from, CarbonImmutable $until, CarbonImmutable $asAt): array
    {
        $range = [$from->startOfDay(), $until->endOfDay()];
        $day = $until->toDateString();
        $housekeepingTypes = array_map(fn (TaskType $type): string => $type->value, array_filter(TaskType::cases(), fn (TaskType $type): bool => $type->isHousekeeping()));

        $inspections = DB::table('inspections')
            ->whereIn('status', [InspectionStatus::Passed->value, InspectionStatus::Failed->value])
            ->whereBetween('inspected_at', $range);
        $scores = DB::table('inspection_scores')
            ->join('inspections', 'inspections.id', '=', 'inspection_scores.inspection_id')
            ->whereBetween('inspections.inspected_at', $range)
            ->selectRaw('avg(inspection_scores.score::numeric / greatest(inspections.scale_max, 1)) as average')
            ->value('average');

        return [
            'arrivals' => Booking::query()->where('arrival_date', $day)->whereIn('status', ['hold', 'pending', 'confirmed', 'checked_in', 'checked_out'])->count(),
            'departures' => Booking::query()->where('departure_date', $day)->whereIn('status', ['checked_in', 'checked_out'])->count(),
            'in_house' => Booking::query()->where('status', BookingStatus::CheckedIn)->count(),
            'attendance' => DB::table('attendance_days')->where('work_date', $day)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($total): int => (int) $total)->all(),
            'active_employees' => DB::table('employees')->where('status', EmployeeStatus::Active->value)->count(),
            'rooms_by_status' => collect(RoomCleaningStatus::cases())->mapWithKeys(fn (RoomCleaningStatus $status): array => [$status->value => Room::query()->active()->where('cleaning_status', $status)->count()])->all(),
            'housekeeping_open' => Task::query()->open()->whereIn('type', $housekeepingTypes)->count(),
            'housekeeping_done' => Task::query()->where('status', TaskStatus::Done)->whereIn('type', $housekeepingTypes)->whereBetween('completed_at', $range)->count(),
            'tasks_overdue' => Task::query()->open()->whereNotNull('due_at')->where('due_at', '<', $asAt)->count(),
            'inspections_done' => (clone $inspections)->count(),
            'inspections_passed' => (clone $inspections)->where('status', InspectionStatus::Passed->value)->count(),
            'inspection_score_percent' => $scores !== null ? round((float) $scores * 100, 1) : null,
            'maintenance_open' => Task::query()->open()->whereNotIn('type', $housekeepingTypes)->count(),
            'faults_reported' => Task::query()->where('source', Task::SOURCE_FAULT)->whereBetween('created_at', $range)->count(),
            'complaints_new' => DB::table('front_desk_logs')->where('type', 'complaint')->whereBetween('created_at', $range)->count(),
            'complaints_open' => DB::table('front_desk_logs')->where('type', 'complaint')->whereNull('resolved_at')->count(),
            'guest_requests_open' => DB::table('guest_requests')->whereIn('status', [GuestRequestStatus::New->value, GuestRequestStatus::Acknowledged->value])->count(),
            'guest_messages_unanswered' => DB::table('guest_messages')->where('direction', 'in')->whereNull('read_at')->count(),
            'exceptions' => [
                'payments_needing_attention' => DB::table('payments')->whereNotNull('exception_reason')->count(),
                'messages_failed' => DB::table('messages')->where('status', MessageStatus::Failed->value)->whereBetween('created_at', $range)->count(),
                'jobs_failed' => DB::table('failed_jobs')->count(),
                'report_deliveries_failed' => DB::table('report_deliveries')->where('status', 'failed')->count(),
            ],
        ];
    }

    /**
     * Created-date basis (REP-06). Formulas in docs/REPORT_METRICS.md.
     *
     * @return array<string, mixed>
     */
    private function marketing(CarbonImmutable $from, CarbonImmutable $until): array
    {
        $range = [$from->startOfDay(), $until->endOfDay()];

        $online = Booking::query()->where('source', 'website')->whereBetween('created_at', $range);
        $started = (clone $online)->count();
        $converted = (clone $online)->whereIn('status', self::SOLD)->count();

        $priceLists = DB::table('price_lists')->whereBetween('created_at', $range);
        $listsSent = (clone $priceLists)->count();
        $listsBooked = (clone $priceLists)->whereNotNull('booking_id')->count();

        $campaigns = Booking::query()
            ->whereBetween('created_at', $range)
            ->whereNotNull('attribution')
            ->selectRaw("coalesce(attribution->>'campaign', attribution->>'source') as campaign, count(*) as made, count(*) filter (where status in ('confirmed', 'checked_in', 'checked_out')) as confirmed")
            ->groupByRaw("coalesce(attribution->>'campaign', attribution->>'source')")
            ->orderByDesc('made')
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($row): array => [(string) $row->campaign => ['made' => (int) $row->made, 'confirmed' => (int) $row->confirmed]])
            ->all();

        return [
            'online_bookings_started' => $started,
            'online_bookings_confirmed' => $converted,
            'online_conversion_percent' => $started > 0 ? round($converted / $started * 100, 1) : null,
            'price_lists_sent' => $listsSent,
            'price_lists_booked' => $listsBooked,
            'price_list_conversion_percent' => $listsSent > 0 ? round($listsBooked / $listsSent * 100, 1) : null,
            'enquiries' => DB::table('enquiries')->whereBetween('created_at', $range)->count(),
            'campaigns' => $campaigns,
        ];
    }
}
