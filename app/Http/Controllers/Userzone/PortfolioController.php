<?php

namespace App\Http\Controllers\Userzone;

use App\Actions\CollectPortfolioInputs;
use App\Actions\DeletePortfolio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Userzone\StorePortfolioRequest;
use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function create(): View
    {
        Gate::authorize('create', Portfolio::class);

        return view('userzone.portfolios.create');
    }

    public function store(StorePortfolioRequest $request, CollectPortfolioInputs $collect): RedirectResponse
    {
        $portfolio = $collect->handle($request->user(), $request->validated(), $request->file('pdf'));

        return to_route('userzone.portfolios.show', $portfolio)->with('status', 'Your PDF and repository evidence are saved privately. Open Generation & progress to review inputs and generate your portfolio.');
    }

    public function show(Portfolio $portfolio): View
    {
        Gate::authorize('view', $portfolio);
        $portfolio->loadCount('projects');
        $portfolio->load('projects');

        return view('userzone.portfolios.show', compact('portfolio'));
    }

    public function destroy(Portfolio $portfolio, DeletePortfolio $delete): RedirectResponse
    {
        Gate::authorize('delete', $portfolio);
        $delete->handle($portfolio);

        return to_route('dashboard')->with('status', 'Draft deleted.');
    }
}
