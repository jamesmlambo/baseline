<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * REP-06: remembers where a website visitor first came from (first touch, this visit only):
 * utm_source / utm_medium / utm_campaign, or else the referring site's host name. No full URLs,
 * IP addresses or cookies beyond the normal session are used.
 */
class CaptureCampaign
{
    public const SESSION_KEY = 'attribution';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->hasSession() && ! $request->session()->has(self::SESSION_KEY)) {
            $attribution = $this->fromRequest($request);

            if ($attribution !== null) {
                $request->session()->put(self::SESSION_KEY, $attribution);
            }
        }

        return $next($request);
    }

    /**
     * @return array<string, string>|null
     */
    private function fromRequest(Request $request): ?array
    {
        $clean = fn (mixed $value): ?string => is_string($value) && trim($value) !== ''
            ? mb_substr((string) preg_replace('/[^A-Za-z0-9 ._\-]/', '', trim($value)), 0, 64) ?: null
            : null;

        $utm = array_filter([
            'source' => $clean($request->query('utm_source')),
            'medium' => $clean($request->query('utm_medium')),
            'campaign' => $clean($request->query('utm_campaign')),
        ]);

        if ($utm !== []) {
            return $utm + ['landing' => mb_substr('/'.ltrim($request->path(), '/'), 0, 120)];
        }

        $referrer = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);

        if (is_string($referrer) && $referrer !== '' && $referrer !== $request->getHost()) {
            return ['source' => mb_substr(mb_strtolower($referrer), 0, 64), 'medium' => 'referral', 'landing' => mb_substr('/'.ltrim($request->path(), '/'), 0, 120)];
        }

        return null;
    }
}
