<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Actions\Website\RecordEnquiry;
use App\Enums\EnquiryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\ContactRequest;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('site.contact', ['page' => Page::forKey('contact')]);
    }

    public function store(ContactRequest $request, RecordEnquiry $record): RedirectResponse
    {
        $record->handle(
            EnquiryType::Contact,
            $request->safe()->only(['name', 'email', 'phone', 'message', 'marketing_consent']),
            $request->ip(),
        );

        return redirect()->route('contact')->with('status', 'Thank you — your message has been sent. We will get back to you soon.');
    }
}
