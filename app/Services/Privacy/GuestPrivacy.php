<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\BookingStatus;
use App\Enums\StaffRole;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * POPIA data-subject requests and the anonymisation framework (PRIV-01/03, COMPLIANCE_TODO).
 *
 * - export(): everything we hold about a guest, for an access request. The export itself is logged.
 * - anonymise(): replaces names and contact details with placeholders everywhere they are stored
 *   for operations (profile, bookings, guest register, messages, portal, leisure). Financial
 *   documents keep the name printed on them, because issued invoices and receipts are immutable
 *   and must be kept for tax (VERIFY retention with an adviser).
 * - Never while a legal hold is set, never for a guest with an open stay, owner only, audited.
 */
class GuestPrivacy
{
    public const PLACEHOLDER = 'Anonymised guest';

    /**
     * @return array<string, mixed>
     */
    public function export(Guest $guest, User $by): array
    {
        if (! $this->managesPrivacy($by)) {
            throw new AuthorizationException('Only the owner or a manager can export a guest\'s data.');
        }

        $bookings = Booking::query()->where('guest_id', $guest->id)->get();
        $bookingIds = $bookings->pluck('id');

        $data = [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'first_name' => $guest->first_name,
                'last_name' => $guest->last_name,
                'email' => $guest->email,
                'phone' => $guest->phone,
                'marketing_consent' => $guest->marketing_consent,
                'marketing_consent_at' => $guest->marketing_consent_at?->toIso8601String(),
                'channel_opt_outs' => $guest->channel_opt_outs,
                'notes' => $guest->notes,
            ],
            'bookings' => $bookings->map(fn (Booking $booking): array => [
                'reference' => $booking->reference,
                'status' => $booking->status->value,
                'arrival' => $booking->arrival_date->toDateString(),
                'departure' => $booking->departure_date->toDateString(),
                'lead_guest_name' => $booking->lead_guest_name,
                'special_requests' => $booking->special_requests,
                'source' => $booking->source->value,
            ])->all(),
            'guest_register' => DB::table('guest_register_entries')->where('guest_id', $guest->id)->orWhereIn('booking_id', $bookingIds)
                ->get(['full_name', 'id_type', 'nationality', 'vehicle_registration', 'number_of_guests', 'created_at'])
                ->map(fn ($row): array => (array) $row + ['id_number' => '(held encrypted; shown at reception on request)'])->all(),
            'messages' => DB::table('messages')->where('guest_id', $guest->id)->orWhereIn('booking_id', $bookingIds)
                ->get(['channel', 'status', 'body', 'created_at'])->map(fn ($row): array => (array) $row)->all(),
            'portal_requests' => DB::table('guest_requests')->whereIn('booking_id', $bookingIds)->get(['type', 'status', 'details', 'created_at'])->map(fn ($row): array => (array) $row)->all(),
            'portal_messages' => DB::table('guest_messages')->whereIn('booking_id', $bookingIds)->get(['direction', 'body', 'created_at'])->map(fn ($row): array => (array) $row)->all(),
            'reviews' => DB::table('reviews')->whereIn('booking_id', $bookingIds)->get(['rating', 'comment', 'display_name', 'consent_to_publish', 'status', 'created_at'])->map(fn ($row): array => (array) $row)->all(),
            'invoices' => DB::table('invoices')->whereIn('booking_id', $bookingIds)->get(['number', 'recipient_name', 'total_cents', 'issued_at'])->map(fn ($row): array => (array) $row)->all(),
        ];

        activity('privacy')->performedOn($guest)->causedBy($by)->event('guest_data_exported')->log('Guest data exported for an access request');

        return $data;
    }

    public function placeHold(Guest $guest, string $reason, User $by): Guest
    {
        if (! $this->managesPrivacy($by)) {
            throw new AuthorizationException('Only the owner or a manager can place a legal hold.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Give a reason for the legal hold.');
        }

        $guest->forceFill(['legal_hold_at' => now(), 'legal_hold_reason' => mb_substr(trim($reason), 0, 255)])->saveQuietly();
        activity('privacy')->performedOn($guest)->causedBy($by)->event('legal_hold_placed')->withProperties(['reason' => trim($reason)])->log('Legal hold placed');

        return $guest;
    }

    public function releaseHold(Guest $guest, User $by): Guest
    {
        if (! $by->hasRole(StaffRole::Owner->value)) {
            throw new AuthorizationException('Only the owner can release a legal hold.');
        }

        $guest->forceFill(['legal_hold_at' => null, 'legal_hold_reason' => null])->saveQuietly();
        activity('privacy')->performedOn($guest)->causedBy($by)->event('legal_hold_released')->log('Legal hold released');

        return $guest;
    }

    public function anonymise(Guest $guest, string $reason, ?User $by): Guest
    {
        if ($by !== null && (! $by->is_active || ! $by->hasRole(StaffRole::Owner->value))) {
            throw new AuthorizationException('Only the owner can anonymise a guest.');
        }

        if ($guest->anonymised_at !== null) {
            throw new InvalidArgumentException('This guest has already been anonymised.');
        }

        if ($guest->legal_hold_at !== null) {
            throw new InvalidArgumentException('This guest is on legal hold: '.$guest->legal_hold_reason);
        }

        $open = Booking::query()->where('guest_id', $guest->id)
            ->whereIn('status', [BookingStatus::Quote, BookingStatus::Hold, BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::CheckedIn])
            ->exists();

        if ($open) {
            throw new InvalidArgumentException('This guest has a booking that is not finished yet.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Give a reason (e.g. the guest asked to be forgotten).');
        }

        DB::transaction(function () use ($guest): void {
            $bookingIds = Booking::query()->where('guest_id', $guest->id)->pluck('id');

            $guest->forceFill([
                'first_name' => 'Anonymised',
                'last_name' => 'guest',
                'email' => null,
                'phone' => null,
                'notes' => null,
                'marketing_consent' => false,
                'channel_opt_outs' => null,
                'anonymised_at' => now(),
            ])->save(); // save(), not saveQuietly(): the saving hook clears the email/phone lookup hashes.

            DB::table('bookings')->whereIn('id', $bookingIds)->update([
                'lead_guest_name' => self::PLACEHOLDER,
                'lead_guest_email' => null,
                'lead_guest_phone' => null,
                'special_requests' => null,
                'attribution' => null,
                'updated_at' => now(),
            ]);

            DB::table('guest_register_entries')->where(fn ($query) => $query->where('guest_id', $guest->id)->orWhereIn('booking_id', $bookingIds))->update([
                'full_name' => self::PLACEHOLDER,
                'id_number' => null,
                'home_address' => null,
                'vehicle_registration' => null,
                'updated_at' => now(),
            ]);

            DB::table('messages')->where(fn ($query) => $query->where('guest_id', $guest->id)->orWhereIn('booking_id', $bookingIds))->update([
                'recipient' => encrypt('anonymised'),
                'recipient_hash' => hash('sha256', 'anonymised:'.$guest->id),
                'body' => '[removed]',
                'updated_at' => now(),
            ]);

            DB::table('guest_messages')->whereIn('booking_id', $bookingIds)->update(['body' => '[removed]', 'updated_at' => now()]);
            DB::table('guest_requests')->whereIn('booking_id', $bookingIds)->update(['details' => null, 'updated_at' => now()]);
            DB::table('reviews')->whereIn('booking_id', $bookingIds)->update(['display_name' => null, 'updated_at' => now()]);
            DB::table('booking_authorized_phones')->whereIn('booking_id', $bookingIds)->update(['phone' => encrypt('anonymised'), 'phone_hash' => hash('sha256', 'anonymised:'.$guest->id), 'label' => null, 'updated_at' => now()]);
            DB::table('service_bookings')->whereIn('booking_id', $bookingIds)->update(['customer_name' => self::PLACEHOLDER, 'customer_phone' => null, 'customer_email' => null, 'notes' => null, 'updated_at' => now()]);

            // Audit entries that copied the name (guest and booking changes) are redacted too.
            DB::table('activity_log')
                ->where(fn ($query) => $query->where(fn ($inner) => $inner->where('subject_type', Guest::class)->where('subject_id', $guest->id))
                    ->orWhere(fn ($inner) => $inner->where('subject_type', Booking::class)->whereIn('subject_id', $bookingIds)))
                ->update(['properties' => json_encode(['redacted' => 'Personal details removed when the guest was anonymised'])]);
        });

        activity('privacy')->performedOn($guest)->causedBy($by)->event('guest_anonymised')->withProperties(['reason' => trim($reason)])->log('Guest anonymised');

        return $guest->refresh();
    }

    private function managesPrivacy(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole([StaffRole::Owner->value, StaffRole::Manager->value]);
    }
}
