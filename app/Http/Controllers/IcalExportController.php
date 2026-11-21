<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\IcalLink;
use App\Services\Channels\IcalCalendar;
use Illuminate\Http\Response;

/**
 * CHN-02: the secret calendar link a channel reads to see when one room is unavailable.
 * Dates only. Unknown or switched-off links give a plain 404.
 */
class IcalExportController extends Controller
{
    public function __invoke(string $token, IcalCalendar $calendar): Response
    {
        $link = IcalLink::query()->where('export_token', $token)->where('export_enabled', true)->first();

        abort_if($link === null, 404);

        return response($calendar->export($link), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
