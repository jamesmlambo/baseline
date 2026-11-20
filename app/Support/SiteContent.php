<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Read-only helpers for the public layout: contact details, navigation and page links.
 * Contact details come only from settings; nothing is hard-coded or invented (WEB-08).
 */
class SiteContent
{
    /** @var Collection<string, Page>|null */
    private ?Collection $pages = null;

    /**
     * @return array{phone: ?string, email: ?string, whatsapp: ?string, address: ?string}
     */
    public function contact(): array
    {
        return [
            'phone' => Setting::get('contact.phone'),
            'email' => Setting::get('contact.email'),
            'whatsapp' => Setting::get('contact.whatsapp'),
            'address' => Setting::get('contact.address'),
        ];
    }

    public function whatsAppUrl(?string $message = null): ?string
    {
        $number = preg_replace('/\D+/', '', (string) Setting::get('contact.whatsapp'));

        if ($number === '' || $number === null) {
            return null;
        }

        if (str_starts_with($number, '0')) {
            $number = '27'.substr($number, 1);
        }

        return 'https://wa.me/'.$number.($message !== null ? '?text='.rawurlencode($message) : '');
    }

    public function page(string $key): ?Page
    {
        return $this->pages()->get($key);
    }

    public function pageUrl(string $key): ?string
    {
        $page = $this->page($key);

        return $page !== null ? route('page', $page->slug) : null;
    }

    public function pageLabel(string $key): ?string
    {
        $page = $this->page($key);

        return $page?->nav_label ?? $page?->title;
    }

    /**
     * Grading stars are only shown once evidence has been verified (WEB-07).
     */
    public function gradingStars(): ?int
    {
        $stars = Setting::get('website.grading_stars');

        return Setting::get('website.grading_verified') === 'yes' && is_numeric($stars) ? (int) $stars : null;
    }

    /**
     * @return Collection<string, Page>
     */
    private function pages(): Collection
    {
        return $this->pages ??= Page::query()->published()->whereNotNull('key')->get()->keyBy('key');
    }
}
