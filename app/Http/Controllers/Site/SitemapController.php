<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AccommodationType;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Http\Response;

/**
 * XML sitemap of every public, published URL (WEB-04).
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'lastmod' => null],
            ['loc' => route('rooms.index'), 'lastmod' => null],
            ['loc' => route('news.index'), 'lastmod' => null],
            ['loc' => route('faqs'), 'lastmod' => null],
            ['loc' => route('availability'), 'lastmod' => null],
        ]);

        Page::query()->published()->get()->each(fn (Page $page) => $urls->push([
            'loc' => route('page', $page->slug),
            'lastmod' => $page->updated_at,
        ]));

        AccommodationType::query()->active()->get()->each(fn (AccommodationType $type) => $urls->push([
            'loc' => route('rooms.show', $type->slug),
            'lastmod' => $type->updated_at,
        ]));

        Post::query()->visible()->get()->each(fn (Post $post) => $urls->push([
            'loc' => route('news.show', $post->slug),
            'lastmod' => $post->updated_at,
        ]));

        return response()
            ->view('site.sitemap', ['urls' => $urls->unique('loc')])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
