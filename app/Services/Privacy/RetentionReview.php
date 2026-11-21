<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\BookingStatus;
use App\Models\Guest;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PRIV-03: configurable retention per data type. Every period is empty until the client decides
 * (PEND-11), and nothing is changed unless `retention.enforcement_enabled` is "yes". review() only
 * counts what *would* be affected, so the owner can see the effect first.
 */
class RetentionReview
{
    public function __construct(private readonly GuestPrivacy $privacy) {}

    /**
     * @return array<string, array{months: ?int, affected: int, action: string}>
     */
    public function review(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $rules = $this->rules();

        return [
            'guest_profiles' => [
                'months' => $rules['guest_profiles'],
                'affected' => $rules['guest_profiles'] === null ? 0 : $this->expiredGuests($now->subMonths($rules['guest_profiles']))->count(),
                'action' => 'Anonymise guests whose last stay ended before the cut-off (not on legal hold, no open bookings).',
            ],
            'messages' => [
                'months' => $rules['messages'],
                'affected' => $rules['messages'] === null ? 0 : DB::table('messages')->where('created_at', '<', $now->subMonths($rules['messages']))->where('body', '<>', '[removed]')->count(),
                'action' => 'Remove the text of SMS/WhatsApp messages (the delivery record stays).',
            ],
            'portal_codes' => [
                'months' => $rules['portal_codes'],
                'affected' => $rules['portal_codes'] === null ? 0 : DB::table('portal_codes')->where('created_at', '<', $now->subMonths($rules['portal_codes']))->count(),
                'action' => 'Delete old guest-portal sign-in codes (hashes only).',
            ],
        ];
    }

    /**
     * Applies the rules. Does nothing unless enforcement is switched on.
     *
     * @return array<string, int>
     */
    public function enforce(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        if (! self::enforcementEnabled()) {
            return [];
        }

        $rules = $this->rules();
        $done = ['guest_profiles' => 0, 'messages' => 0, 'portal_codes' => 0];

        if ($rules['guest_profiles'] !== null) {
            $this->expiredGuests($now->subMonths($rules['guest_profiles']))->each(function (Guest $guest) use (&$done): void {
                $this->privacy->anonymise($guest, 'Retention period ended (automatic)', null);
                $done['guest_profiles']++;
            });
        }

        if ($rules['messages'] !== null) {
            $done['messages'] = DB::table('messages')->where('created_at', '<', $now->subMonths($rules['messages']))->where('body', '<>', '[removed]')
                ->update(['body' => '[removed]', 'updated_at' => now()]);
        }

        if ($rules['portal_codes'] !== null) {
            $done['portal_codes'] = DB::table('portal_codes')->where('created_at', '<', $now->subMonths($rules['portal_codes']))->delete();
        }

        activity('privacy')->event('retention_enforced')->withProperties($done)->log('Retention rules applied');

        return $done;
    }

    public static function enforcementEnabled(): bool
    {
        return in_array(mb_strtolower((string) Setting::get('retention.enforcement_enabled', 'no')), ['yes', 'true', '1', 'on'], true);
    }

    /**
     * @return array{guest_profiles: ?int, messages: ?int, portal_codes: ?int}
     */
    private function rules(): array
    {
        $months = fn (string $key): ?int => is_numeric($value = Setting::get($key)) && (int) $value > 0 ? (int) $value : null;

        return [
            'guest_profiles' => $months('retention.guest_profile_months'),
            'messages' => $months('retention.message_months'),
            'portal_codes' => $months('retention.portal_code_months'),
        ];
    }

    /**
     * @return Builder<Guest>
     */
    private function expiredGuests(CarbonImmutable $cutOff)
    {
        $active = [BookingStatus::Quote, BookingStatus::Hold, BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::CheckedIn];

        return Guest::query()
            ->whereNull('anonymised_at')
            ->whereNull('legal_hold_at')
            ->whereDoesntHave('bookings', fn ($query) => $query->whereIn('status', $active))
            ->whereDoesntHave('bookings', fn ($query) => $query->where('departure_date', '>=', $cutOff->toDateString()))
            ->where('created_at', '<', $cutOff);
    }
}
