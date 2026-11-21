<?php

declare(strict_types=1);

namespace App\Actions\Website;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Http\Middleware\CaptureCampaign;
use App\Models\Enquiry;
use App\Models\Setting;
use App\Notifications\NewWebsiteEnquiry;
use Illuminate\Support\Facades\Notification;

/**
 * Stores a website enquiry and notifies the enquiry inbox if one is configured.
 * The visitor's IP is kept only as a keyed hash, for abuse checks (PRIV-01).
 */
class RecordEnquiry
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(EnquiryType $type, array $attributes, ?string $ip): Enquiry
    {
        $enquiry = Enquiry::query()->create([
            ...$attributes,
            'type' => $type,
            'status' => EnquiryStatus::New,
            'marketing_consent' => (bool) ($attributes['marketing_consent'] ?? false),
            'ip_hash' => $ip !== null ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
        ]);

        // REP-06: first-touch campaign source for this visit, if any.
        $attribution = request()->hasSession() ? request()->session()->get(CaptureCampaign::SESSION_KEY) : null;

        if (is_array($attribution)) {
            $enquiry->forceFill(['attribution' => $attribution])->save();
        }

        $inbox = Setting::get('website.enquiry_email');

        if (is_string($inbox) && filter_var($inbox, FILTER_VALIDATE_EMAIL)) {
            Notification::route('mail', $inbox)->notify(new NewWebsiteEnquiry($enquiry));
        }

        return $enquiry;
    }
}
