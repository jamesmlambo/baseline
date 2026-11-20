<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug): View|RedirectResponse
    {
        $page = Page::query()->published()->with('media')->where('slug', $slug)->first();

        if ($page === null) {
            // Not a CMS page: let the redirect table have a go before giving up (WEB-05).
            return app(RedirectController::class)(request());
        }

        if ($page->key === 'contact') {
            return app(ContactController::class)->show();
        }

        return view('site.page', [
            'page' => $page,
            'amenities' => $page->key === 'features'
                ? Amenity::query()->published()->get()->groupBy(fn (Amenity $amenity): string => $amenity->category->label())
                : collect(),
            'serviceKeys' => $page->key === 'services' ? ['pool', 'braai', 'vip', 'spa'] : [],
        ]);
    }
}
