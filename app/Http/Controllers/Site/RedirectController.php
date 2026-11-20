<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Sends old-site URLs to their new home with a 301/302 (WEB-05), and counts hits so unused
 * redirects can be cleaned up later.
 */
class RedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $redirect = Redirect::query()
            ->where('is_active', true)
            ->where('from_path', Redirect::normalisePath($request->getPathInfo()))
            ->first();

        if ($redirect === null) {
            throw new NotFoundHttpException;
        }

        $redirect->newQuery()->whereKey($redirect->id)->update([
            'hits' => $redirect->hits + 1,
            'last_hit_at' => now(),
        ]);

        return redirect()->to($redirect->to_path, $redirect->status_code);
    }
}
