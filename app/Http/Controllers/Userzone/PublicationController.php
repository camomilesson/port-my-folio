<?php

namespace App\Http\Controllers\Userzone;

use App\Actions\PublishPortfolio;
use App\Actions\SetDefaultPortfolio;
use App\Actions\UnpublishPortfolio;
use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PublicationController extends Controller
{
    public function confirm(Portfolio $portfolio): View
    {
        Gate::authorize('update', $portfolio);

        return view('userzone.portfolios.publish', compact('portfolio'));
    }

    public function publish(Request $request, Portfolio $portfolio, PublishPortfolio $publish): RedirectResponse
    {
        Gate::authorize('update', $portfolio);
        $request->validate(['confirm_publication' => ['required', 'accepted']]);
        $publish->handle($portfolio);

        return to_route('userzone.portfolios.show', $portfolio)->with('status', 'Portfolio published. Your saved portfolio and original CV PDF are public.');
    }

    public function unpublish(Portfolio $portfolio, UnpublishPortfolio $unpublish): RedirectResponse
    {
        Gate::authorize('update', $portfolio);
        $unpublish->handle($portfolio);

        return back()->with('status', 'Portfolio unpublished. Public pages and new file downloads are disabled.');
    }

    public function makeDefault(Portfolio $portfolio, SetDefaultPortfolio $default): RedirectResponse
    {
        Gate::authorize('update', $portfolio);
        $default->handle($portfolio);

        return back()->with('status', 'Default portfolio updated.');
    }
}
