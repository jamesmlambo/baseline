<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function __invoke(): View
    {
        return view('site.faqs', [
            'groups' => Faq::query()->published()->get()->groupBy(fn (Faq $faq): string => $faq->category ?: 'General'),
        ]);
    }
}
