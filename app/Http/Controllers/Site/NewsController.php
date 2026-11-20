<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function index(): View
    {
        return view('site.news.index', [
            'posts' => Post::query()->visible()->with('media')->latest('published_at')->paginate(9),
        ]);
    }

    public function show(string $slug): View
    {
        $post = Post::query()->visible()->with('media')->where('slug', $slug)->firstOrFail();

        return view('site.news.show', ['post' => $post]);
    }
}
